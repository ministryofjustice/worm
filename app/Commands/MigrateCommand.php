<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Container\Container;

class MigrateCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'migrate { source : Environment you are migrating from } { target : Environment you are migrating to }
                            {--blogID= : Blog ID of single site you wish to migrate }
                            {--keepProdDomain= : Keep production domain even if migrating to non-prod env (true or false)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Migrate site(s) from one environment to another, prod, staging, dev, demo & local';

    /**
     * The source environment.
     *
     * @var string|null
     */
    protected $source;

    /**
     * The target environment.
     *
     * @var string|null
     */
    protected $target;

    /**
     * The sourceNamespace.
     *
     * @var string|null
     */
    protected $sourceNamespace;

    /**
     * The pod name.
     *
     * @var string|null
     */
    protected $podName;

    protected $servicePodName;
    /**
     * The SQL file name.
     *
     * @var string|null
     */
    protected $sqlFile;

    /**
     * The path.
     *
     * @var string|null
     */
    protected $path;

    /**
     * The profile name.
     *
     * @var string|null
     */
    protected $profile;

    /**
     * Site array.
     *
     * @var array|null
     */
    protected $sites;

    protected $secretName;
    /**
     * Blog ID.
     *
     * @var string|null
     */
    protected $blogID;

    /**
     * Single Site SQL file name
     *
     * @var string|null
     */
    protected $sqlFileSingleSite;

    /**
     * If migrating to a non-prod env keep domain
     *
     * @var bool|false
     */
    protected $keepProdDomain;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Command imputs
        $this->source = $this->argument('source');
        $this->target = $this->argument('target');
        $this->blogID = is_numeric($this->option('blogID')) ? $this->option('blogID') : null;
        $this->keepProdDomain = $this->option('keepProdDomain') ? $this->option('keepProdDomain') : null;

        // Set namespace and file naming
        $this->sourceNamespace = "hale-platform-$this->source";

        // Add the blog ID into the temp file if its a single site migration
        $siteID = ($this->blogID !== null) ? 'site' . $this->blogID . '-' : '';
        $this->sqlFile = $this->sourceNamespace . '-' . $siteID . date("Y-m-d-H-i-s") . '.sql';

        $this->path = rtrim(shell_exec('pwd'));
        $this->profile = "$this->sourceNamespace-s3";

        // SSOT hardcoded list of production domains
        // List can be updated in the SiteList.php
        $container = Container::getInstance();
        $this->sites = $container->get('sites');

        // Do not allow migration from local to CP environments
        if ($this->source === 'local') {
            $this->info("Worming from local => $this->target currently not a feature.");
            return;
        }

        // Warn when migrating to prod
        if ($this->target === 'prod') {
            // Force manual approval prompt you are migrating to prod
            $proceed = $this->ask("### WARNING ### You are migrating to prod. Do you wish to proceed? [n/y]");

            // If not "yes", then exit.
            if ($proceed != 'yes' && $proceed != 'y') {
                $this->info("Migration to prod canceled. Exiting task.");
                exit(0);
            }
        }

        // Account for typos wrong env entered in
        if (!in_array($this->source, ['prod', 'staging', 'dev', 'demo'])) {
            $this->info("Worm cannot find the $this->source environment.");
            return;
        }

        // Check the CloudPlatform cli is up-to-date otherwise things will break silently
        $this->updateCloudPlatformCli();

        // Migrating from CloudPlatform to local Docker site
        if ($this->target === 'local') {
            $this->migrateToLocalEnv(
                $this->source,
                $this->sqlFile,
                $this->target,
                $this->sites,
                $this->path,
                $this->blogID
            );
            return;
        }

        // Migrating between CloudPlatform environments
        if (in_array($this->target, ['prod', 'staging', 'dev', 'demo'])) {
            $this->migrateBetweenCloudPlatformEnv(
                $this->source,
                $this->sqlFile,
                $this->target,
                $this->sites,
                $this->path,
                $this->blogID
            );
            return;
        }

        $this->info('Target environment not found.');
    }

    /**
     * Migrates the data from a CloudPlatform env to the local environment.
     *
     * @param string $source The env name of the CloudPlatform namespace, ie prod, staging.
     * @param string $sqlFile The SQL database file name.
     * @param string $target The target container migrating to.
     * @param string $path The local machine path to present directory.
     * @param int|null $blogID The ID of the single site to migrate. Null for multisite migration.
     * @return void
     */
    public function migrateToLocalEnv(
        string $source,
        string $sqlFile,
        string $target,
        array $sites,
        string $path,
        ?int $blogID
    ): void {
        // Check that Docker is running locally
        !$this->isDockerRunning() ? $this->info("Docker is not running on the local computer.") : null;

        // Check the local repo has the right folder structure for media items
        $this->checkWordPressFolderExists($path);

        // Add migration specific text
        $typeOfMigration = ($blogID !== null) ? "single site ID:$blogID" : "multisite";

        $this->info("🐛 Starting $typeOfMigration migration " . ucfirst($this->source) . " => Local");

        // DB export from RDS
        if ($blogID != null) {
            $this->exportSingleSiteDatabase($source, $blogID);
        } else {
            $this->exportMultisiteDatabase($source, $sqlFile);
        }

        // Copy SQL file from the container to the local machine
        !$this->copySqlFileToLocal($source, $sqlFile) ? exit(1) : null;

        // Delete SQL file from the container
        !$this->deleteSqlFileFromContainer($source, $sqlFile) ? exit(1) : null;

        // Copy SQL file from the local machine to the target container
        !$this->copySqlFileToLocalContainer($sqlFile) ? exit(1) : null;

        // Delete the temporary SQL file from the local machine
        !$this->deleteSqlFileFromLocal($path, $sqlFile) ? exit(1) : null;

        // Copy SQL file from container into local MariaDB database
        $this->importDbIntoLocalMariaDb($sqlFile);

        // Delete SQL file from the container
        !$this->deleteSqlFileFromContainer($target, $sqlFile) ? exit(1) : null;

        // Rewrite URLs to match the local hale.docker URL
        !$this->replaceDatabaseURLsLocal() ? exit(1) : null;

        // Perform additional domain rewrite for migrations coming from production
        if (in_array($this->source, ['prod']) || $this->keepProdDomain == 'false') {
            $this->nonProductionDatabaseDomainRewrite($target, $sites, $blogID);
        }

        // Perform additional domain rewrite when migrating from non-prod => production
        if (in_array($target, ['prod']) || $this->keepProdDomain == 'true') {
            $this->productionDatabaseDomainRewrite($target, $sites, $blogID);
        }

        // Sync remote s3 bucket with local
        $this->syncS3BucketToLocal($source, $path, $blogID);

        // Migration completed successfully
        $this->info("Migration $typeOfMigration complete 🐛. " . ucfirst($source) . " has been migrated to " . ucfirst($target));
    }

    /**
     * Migrate between CloudPlatform environments.
     *
     * This method facilitates the migration process between CloudPlatform environments. It exports the database from the source environment, copies the SQL file to the target environment, performs necessary modifications, and syncs S3 bucket media assets. The migration can be performed for a single site or multisite installation.
     *
     * @param string $source The source environment.
     * @param string $sqlFile The SQL file to migrate.
     * @param string $target The target environment.
     * @param array  $sites An array of production site meta.
     * @param string $path The path to the SQL file.
     * @param int|null $blogID The ID of the single site to migrate. Null for multisite migration.
     * @return void
     */
    public function migrateBetweenCloudPlatformEnv(
        string $source,
        string $sqlFile,
        string $target,
        array $sites,
        string $path,
        ?int $blogID
    ): void {
        // Add migration specific text
        $typeOfMigration = ($blogID !== null) ? "single site ID:$blogID" : "multisite";

        $this->info("Starting $typeOfMigration migration 🐛. " . ucfirst($source) . " => " . ucfirst($target));

        // DB export from RDS
        if ($blogID !== null) {
            $this->exportSingleSiteDatabase($source, $blogID);
        } else {
            $this->exportMultisiteDatabase($source, $sqlFile);
        }

        // Copy SQL file from the container to the local machine
        $this->copySqlFileToLocal($source, $sqlFile);

        // Delete SQL file from the container
        $this->deleteSqlFileFromContainer($source, $sqlFile);

        // Copy SQL file from the local machine to the target container
        $this->copySqlFileToContainer($target, $sqlFile);

        // Delete the temporary SQL file from the local machine
        $this->deleteSqlFileFromLocal($path, $sqlFile);

        // Import the new database into the target RDS instance
        $this->importSqlFromContainerToRds($target, $sqlFile);

        // Delete SQL file from the target container
        $this->deleteSqlFileFromContainer($target, $sqlFile);

        // Rewrite URLs to match the target environment
        $this->replaceDatabaseURLs($target, $blogID);

        // Perform additional domain rewrite for production => non-prod environments
        if (in_array($source, ['prod']) || $this->keepProdDomain == 'false') {
            $this->nonProductionDatabaseDomainRewrite($target, $sites, $blogID);
        }

        // Perform additional domain rewrite when migrating from non-prod => production
        if (in_array($target, ['prod']) || $this->keepProdDomain == 'true') {
            $this->productionDatabaseDomainRewrite($target, $sites, $blogID);
        }

        // Update s3 bucket media assets, docs, images, etc. with the target environment
        $this->syncS3BucketWithTarget($source, $target, $blogID);

        // Migration completed successfully
        $this->info("Migration $typeOfMigration complete 🐛. " . ucfirst($source) . " has been migrated to " . ucfirst($target));
    }

    /**
     * Get the pod name for the specified namespace.
     *
     * @param string $env The namespace to get the pod name from.
     * @return string|null The pod name or null if not found.
     */
    private function getPodName($env)
    {

        // Guard clause, we don't need to get a pod in the local environment
        if ($env === 'local') {
            return null;
        }

        $command = "kubectl get pods -n hale-platform-$env -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'";
        $podName = rtrim(shell_exec($command));

        return $podName ?: null;
    }

    /**
     * Get the pod name for the specified namespace.
     *
     * @param string $env The namespace to get the pod name from.
     * @return string|null The pod name or null if not found.
     */
    private function getServicePodName($env)
    {

        // Guard clause, we don't need to get a pod in the local environment
        if ($env === 'local') {
            return null;
        }

        $command = "kubectl get pods -n hale-platform-$env -o=name | grep -m 1 service-pod | sed 's/^.\{4\}//'";
        $podName = rtrim(shell_exec($command));

        return $podName ?: null;
    }

    /**
     * Copy file from pod to local machine.
     *
     * @param string $env The namespace.
     * @param string $sqlFile The SQL file name.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copySqlFileToLocal($env, $sqlFile)
    {
        $podName = $this->getPodName($env);

        return $this->task("=> Copy temporary SQL file to local directory.", function () use ($env, $podName, $sqlFile) {
            $command = "kubectl cp --retries=10 -n hale-platform-$env -c wordpress $podName:$sqlFile $sqlFile";
            passthru($command, $resultCode);

            if ($resultCode === 0) {
                return true;
            } else {
                $this->handleFailure("Failed to copy database from container to local machine. Exiting task with error code: $resultCode");
                exit($resultCode);
            }
        });
    }

    /**
     * Copy source database file on local machine to the target container.
     *
     * @param string $env The namespace.
     * @param string $sqlFile The destination path in the container.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copySqlFileToContainer($env, $sqlFile)
    {
        $podName = $this->getPodName($env);

        $resultCode = $this->task("=> Copy temporary SQL file to target container.", function () use ($podName, $env, $sqlFile) {
            passthru("kubectl cp --retries=10 -n hale-platform-$env -c wordpress $sqlFile hale-platform-$env/$podName:$sqlFile", $resultCode);
            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        if (!$resultCode) {
            $this->handleFailure("Failed to copy SQL file from local machine to remote container. Exiting task.");
            return false;
        }

        return true;
    }

    /**
     * Export the database from RDS to the container.
     *
     * @param string $sqlFile The SQL file path.
     * @return bool True if the export is successful; otherwise, false.
     */
    private function exportMultisiteDatabase($env, $sqlFile)
    {
        $containerExecCommand = $this->getExecCommand($env);

        return $this->task("=> Export multisite database", function () use ($containerExecCommand, $sqlFile) {
                $command = "$containerExecCommand wp db export --porcelain $sqlFile";
                $output = shell_exec($command);
        });
    }

    /**
     * Export the database tables associated with a single site.
     *
     * @param string $env The namespace to get the pod name from.
     */
    private function exportSingleSiteDatabase($env, $blogID)
    {
        $containerExecCommand = $this->getExecCommand($env);

        # Get Single Blog Table Names
        $tableNames = rtrim(shell_exec("$containerExecCommand wp db tables 'wp_{$blogID}_*' --all-tables-with-prefix --format=csv"));

        if (count(explode(",", $tableNames)) < 10) {
            $this->info('Not all blog tables found');
            return;
        }

        $this->task("=> Export single site database", function () use ($containerExecCommand, $tableNames) {
                $output = shell_exec("$containerExecCommand wp db export --porcelain $this->sqlFile --tables='$tableNames'");
        });
    }

    /**
     * Import the database into RDS.
     *
     * @param string $env The namespace.
     * @param string $sqlFile The SQL file to import.
     * @return bool True if the import is successful; otherwise, false.
     */
    private function importSqlFromContainerToRds($env, $sqlFile)
    {
        $containerExecCommand = $this->getExecCommand($env);

        $command = "$containerExecCommand wp db import $sqlFile";
        passthru($command, $resultCode);

        if ($resultCode === 0) {
            return true;
        } else {
            $this->handleFailure("Failed to import the database into RDS from target container. Exiting task.");
            return false;
        }
    }

    /**
     * Get the execution command for either Docker or Kubernetes
     *
     * @param string $env the specific environment you want to exec in.
     * @return string The pod execution command.
     */
    private function getExecCommand($env)
    {
        // When running WORM on local use docker commands
        if ($env === 'local') {
            return "docker exec -it wordpress ";
        }

        $podName = $this->getPodName($env);

        return "kubectl exec -it -n hale-platform-$env -c wordpress pod/$podName --";
    }

    /**
     * Delete the SQL file in the container.
     *
     * @param string $env The namespace.
     * @param string $sqlFile The SQL file to delete.
     * @return bool Indicates whether the deletion was successful or not.
     */
    private function deleteSqlFileFromContainer($env, $sqlFile)
    {
        $containerExecCommand = $this->getExecCommand($env);

        return $this->task("=> Delete container SQL file. No longer needed.", function () use ($containerExecCommand, $sqlFile) {
            passthru("$containerExecCommand rm $sqlFile", $resultCode);

            if ($resultCode !== 0) {
                $this->handleFailure("Failed to delete $sqlFile from container. Exiting task.");
                return false;
            }

            return true;
        });
    }

    /**
     * Delete SQL file on local machine.
     *
     * @param string $path The path of the SQL file.
     * @param string $sqlFile The SQL file name.
     * @return bool True if the deletion is successful; otherwise, false.
     * @return bool True if the deletion is successful; otherwise, false.
     */
    private function deleteSqlFileFromLocal($path, $sqlFile)
    {
        $command = "rm $path/$sqlFile";
        passthru($command, $resultCode);

        if ($resultCode === 0) {
            return true;
        } else {
            $this->handleFailure("Failed to delete SQL file on local machine. Exiting task.");
            exit($resultCode);
        }
    }

    /**
     * Replace database URLs to match the target environment.
     *
     * @param string $env The target environment name.
     * @return bool True if the URL replacement is successful; otherwise, false.
     */
    private function replaceDatabaseURLs($env, $blogID)
    {
        // Define the old and new URLs based on the environment names

        $sourceSiteURL = "$this->source.websitebuilder.service.justice.gov.uk";

        $targetSiteURL = "$this->target.websitebuilder.service.justice.gov.uk";

        // Get the pod execution command for the specified environment
        $containerExecCommand = $this->getExecCommand($env);

        if ($this->blogID !== null) {
            $urlFlag = "--url=$targetSiteURL 'wp_{$blogID}_*' --all-tables-with-prefix";
        } else {
            $urlFlag = "--url=$sourceSiteURL";
        }

        // Execute the URL replacement command
        $command = "$containerExecCommand wp search-replace $sourceSiteURL $targetSiteURL $urlFlag --network --precise --skip-columns=guid --report-changed-only --recurse-objects";
        passthru($command, $resultCode);

        // Check the result code and handle success or failure
        if ($resultCode === 0) {
            return true;
        } else {
            $this->handleFailure("Failed to replace database URLs. Exiting task with error code: $resultCode");
            return false;
        }
    }

    /**
     * Perform domain rewrite when migrating from prod => non-prod environments
     * @param string $env The target environment name.
     * @param array $sites The array of site details.
     */
    private function nonProductionDatabaseDomainRewrite($env, array $sites, $blogID)
    {
        $infoMsg = "[*] Rewrite domains, prod to non-prod domain" . PHP_EOL;

        $containerExecCommand = $this->getExecCommand($env);

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['slug'];
            $siteID = $site['blogID'];

                       

            // Only run the rewrite code once and for the matching single site and finish
            if ($this->blogID !== null && $blogID === $siteID) {
                $this->info($infoMsg);
                $this->performDomainRewriteNonProd($domain, $sitePath, $containerExecCommand, $siteID);
            }

            // If we have no blog id being used we are migrating a whole site so loop without restrictions
            if ($this->blogID === null) {
                $this->info($infoMsg);
                $this->performDomainRewriteNonProd($domain, $sitePath, $containerExecCommand, $siteID);
            }
        }
    }

    /**
     * Perform domain rewrite when migrating from a non-prod => prod environment
     * For example:
     * $this->target.websitebuilder.service.justice.gov.uk/ccrc -> ccrc.gov.uk
     *
     * @param string $env The target environment name.
     * @param array $sites The array of site details.
     */
    private function productionDatabaseDomainRewrite($env, array $sites, $blogID)
    {

        $this->info("Run domain rewrite to keep production domain.");

        $containerExecCommand = $this->getExecCommand($env);

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['path'];
            $siteID = $site['blogID'];

            // Only run the rewrite code once and for the matching single site and finish
            if ($this->blogID !== null && $blogID === $siteID) {
                $this->performDomainRewriteProd($domain, $sitePath, $containerExecCommand, $siteID);
                return;
            }

            // If we have no blog id being used we are migrating a whole site so loop without restrictions
            if ($this->blogID === null) {
                $this->performDomainRewriteProd($domain, $sitePath, $containerExecCommand, $siteID);
            }
        }
    }

    /**
     * Syncs an S3 bucket with the target environment.
     *
     * This method synchronizes files between two Amazon S3 buckets using the AWS CLI.
     * The source S3 bucket is specified by the `$source` parameter, and the target S3 bucket is specified by the `$target` parameter.
     * The sync operation copies files from the source bucket to the target bucket,
     * ensuring that the target bucket matches the contents of the source bucket.
     *
     * @param string $source The source environment representing the name of the source S3 bucket.
     * @param string $target The target environment representing the name of the target S3 bucket.
     */
    public function syncS3BucketWithTarget($source, $target, $blogID)
    {

        $containerExecCommand = $this->getExecCommand($target);
        $servicePodName = $this->getServicePodName($source);

        $targetSiteURL = "$target.websitebuilder.service.justice.gov.uk";

        $sourceBucketsecretName = $this->getSecretName($source);
        $sourceBucketsecrets = $this->decodeSecrets($source);

        $targetBucketsecretName = $this->getSecretName($target);
        $targetBucketsecrets = $this->decodeSecrets($target);

        $sourceBucketjson_secrets = json_decode($sourceBucketsecrets, true);
        $sourceBucket = $sourceBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        $targetBucketjson_secrets = json_decode($targetBucketsecrets, true);
        $targetBucket = $targetBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        // Change uploads path depending of if single site or whole ms migration
        $uploadsDir = ($blogID != null) ? "uploads/sites/$blogID" : "uploads";

        // Replace the s3 bucket name
        $this->stringReplaceS3BucketName($sourceBucket, $targetBucket, $targetSiteURL, $containerExecCommand, $blogID);

        $this->info('=> AWS s3 bucket sync');

        passthru("kubectl exec -it -n hale-platform-$source $servicePodName -- bin/sh -c \"aws s3 sync s3://$sourceBucket/$uploadsDir s3://$targetBucket/$uploadsDir --acl=public-read\"");
    }

    /**
     * Syncs an S3 bucket with the local machine.
     *
     * This method synchronizes files between an Amazon S3 bucket and the local machine using the AWS CLI.
     *
     * @param string $source The source environment representing the name of the S3 bucket.
     * @param string $path The local path where the files should be synced to.
     */
    private function syncS3BucketToLocal($source, $path, $blogID)
    {
        $uploadsPath = $path . "/wordpress/wp-content";

        $sourceBucketsecretName = $this->getSecretName($source);
        $sourceBucketsecrets = $this->decodeSecrets($source);
        $sourceBucketjson_secrets = json_decode($sourceBucketsecrets, true);
        $sourceBucket = $sourceBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        // Change uploads path depending of if single site or whole ms migration
        $uploadsDir = ($blogID != null) ? "uploads/sites/$blogID" : "uploads";

        $uploadsDir = ($blogID != null) ? "uploads/sites/$blogID" : "uploads";

        $this->info("Sync $source s3 bucket with local uploads directory");
        passthru("aws s3 sync --profile hale-platform-$source-s3 s3://$sourceBucket/$uploadsDir $uploadsPath", $resultCode);
    }

    /**
     * Get the secret name.
     *
     * @param string $env The environment name.
     */
    private function getSecretName($env)
    {
        // Local environment doesn't have secrets
        if ($env === 'local') {
            return;
        }

        $podName = $this->getPodName($env);

        $command = "kubectl describe -n hale-platform-$env pods/$podName | grep -o 'hale-wp-secrets-[[:digit:]]*'";
        $output = shell_exec($command);

        return rtrim($output);
    }

    /**
     * Decode the secrets.
     *
     * @param string $env The environment name.
     */
    private function decodeSecrets($env)
    {
        // Local environment doesn't have secrets
        if ($env === 'local') {
            return;
        }

        $this->secretName = $this->getSecretName($env);

        $command = "cloud-platform decode-secret -n hale-platform-$env -s $this->secretName";
        $output = shell_exec($command);

        return rtrim($output);
    }

    /**
     * Handle the failure by displaying an error message.
     *
     * @param string $errorMessage The error message.
     * @return void
     */
    private function handleFailure($errorMessage)
    {
        $this->info($errorMessage);
    }

    /**
     * Check if Docker is running on the local computer.
     *
     * @return bool True if Docker is running; otherwise, false.
     */
    private function isDockerRunning()
    {
        $command = 'docker info >/dev/null 2>&1 && echo "Docker is running" || echo "Docker is not running"';

        // Execute the command and capture the output
        exec($command, $output, $resultCode);

        // Check if the output contains the expected string
        if ($resultCode === 0 && !empty($output) && $output[0] === 'Docker is running') {
            return true;
        }

        return false;
    }

    /**
     * Copy SQL file from local machine to Docker container.
     *
     * @param string $sqlFile The SQL file to copy.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copySqlFileToLocalContainer($sqlFile)
    {
        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));

        $resultCode = $this->task("=> Copying database from local machine to target container.", function () use ($containerID, $sqlFile) {
            passthru("docker cp $sqlFile $containerID:/var/www/html/$sqlFile", $resultCode);
            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        return $resultCode;
    }

    /**
     * Import the database into the local MariaDB.
     *
     * @param string $sqlFile The SQL file to import.
     * @return bool True if the import is successful; otherwise, false.
     */
    private function importDbIntoLocalMariaDb($sqlFile)
    {
        $resultCode = 0;

        passthru("docker exec -it wordpress wp db import $sqlFile", $resultCode);

        $resultCode = ($resultCode === 0) ? true : false;
        return $resultCode;
    }

    /**
     * Perform string replace on the imported database.
     * @return bool True if the string replace is successful; otherwise, false.
     */
    private function replaceDatabaseURLsLocal()
    {

        // Define the old and new URLs based on the environment names
        $sourceSiteURL = "$this->source.websitebuilder.service.justice.gov.uk";
        $targetSiteURL = "hale.docker";

        if ($this->source === 'prod') {
            $sourceSiteURL = "websitebuilder.service.justice.gov.uk";
        }

        if ($this->blogID !== null) {
            $urlFlag = "--url=$targetSiteURL 'wp_{$this->blogID}_*' --all-tables-with-prefix";
        } else {
            $urlFlag = "--url=$sourceSiteURL";
        }

        $command = "docker exec -it wordpress wp search-replace $sourceSiteURL $targetSiteURL $urlFlag --network --precise --skip-columns=guid --report-changed-only --recurse-objects";

        // Execute the command and capture the result code
        passthru($command, $resultCode);

        $resultCode = ($resultCode === 0) ? true : false;
        return $resultCode;
    }

    /**
     * Check if the /wordpress folder exists locally.
     *
     * This method checks whether the /wordpress folder exists in the specified directory.
     *
     * @param string $path The directory path to check.
     * @return bool True if the /wordpress folder exists; otherwise, false.
     */
    private function checkWordPressFolderExists($path)
    {
        $wordpressPath = $path . "/wordpress";
        $wordpressPathText =
        'Wordpress installation not found. Check you are in the root directory of the hale-platform repo and
        have already run the site locally.';

        if (!is_dir($wordpressPath)) {
            $this->info($wordpressPathText);
            exit(0);
        }

        return true;
    }

    /**
     * Update and upgrade the cloud-platform-cli package using Homebrew.
     *
     * This method updates Homebrew and upgrades the cloud-platform-cli package to the latest version.
     *
     * @return void
     */
    private function updateCloudPlatformCli()
    {
        $this->info("Check cloud-platform-cli is updated to the latest version");

        exec("brew update && brew upgrade cloud-platform-cli 2>/dev/null", $output, $resultCode);

        if ($resultCode !== 0) {
            $this->handleFailure("Failed to update and upgrade cloud-platform-cli. Exiting task.");
            exit(1);
        }
    }

    /**
     * Perform domain rewrite non-prod to prod
     *
     * @param string $domain The domain of the site.
     * @param string $sitePath The path of the site.
     * @param string $containerExecCommand The command to execute in the container.
     * @param int $siteID The ID of the site.
     * @return void
     */
    private function performDomainRewriteProd(string $domain, string $sitePath, string $containerExecCommand, int $siteID)
    {
        $domainPath = "$this->target.websitebuilder.service.justice.gov.uk/$sitePath";

        $this->info($domain);

         // Search and replace only on the correct URL and blog ID if is single site migration
        if ($this->blogID !== null) {
            // Handles whether you are migrating to a domain on prod or the hale-platform infrastructure domain
            // as we can have both types on production.
            if ($domain !== null) {
                $urlFlag = "--url=$domain 'wp_{$siteID}_*' --all-tables-with-prefix";
            } else {
                $urlFlag = "--url=$domainPath 'wp_{$siteID}_*' --all-tables-with-prefix";
            }
        } else {
            $urlFlag = "--url=$domainPath";
        }

        passthru("$containerExecCommand wp search-replace '$domainPath' 'https://$domain' $urlFlag --network --skip-columns=guid --report-changed-only");
        passthru("$containerExecCommand wp db query 'UPDATE wp_blogs SET domain=\"$domain\" WHERE wp_blogs.blog_id=$siteID'");
        passthru("$containerExecCommand wp db query 'UPDATE wp_blogs SET path=\"/\" WHERE wp_blogs.blog_id=$siteID'");
    }

    /* Perform domain rewrite prod to non-prod
     *
     * @param string $domain The domain of the site.
     * @param string $sitePath The path of the site.
     * @param string $containerExecCommand The command to execute in the container.
     * @param int $siteID The ID of the site.
     * @return void
     */
    private function performDomainRewriteNonProd(string $domain, string $sitePath, string $containerExecCommand, int $siteID)
    {

        if ($this->target === 'local') {
            $newDomainPath = "hale.docker";
            $domainPath = "https://hale.docker/$sitePath";
        } else {
            $newDomainPath = "$this->target.websitebuilder.service.justice.gov.uk";
            $domainPath = "https://$this->target.websitebuilder.service.justice.gov.uk/$sitePath";
        }

        $this->info($domain);

        // Search and replace only on the correct URL and blog ID if is single site migration
        if ($this->blogID !== null) {
            $urlFlag = "--url=$domain 'wp_{$siteID}_*' --all-tables-with-prefix";
        } else {
            $urlFlag = "--url=$domain";
        }

        passthru("$containerExecCommand wp search-replace 'https://$domain' '$domainPath' $urlFlag --network --skip-columns=guid --report-changed-only");
        passthru("$containerExecCommand wp db query 'UPDATE wp_blogs SET domain=\"$newDomainPath\" WHERE wp_blogs.blog_id=$siteID'");
        passthru("$containerExecCommand wp db query 'UPDATE wp_blogs SET path=\"/$sitePath/\" WHERE wp_blogs.blog_id=$siteID'");


        if ($this->target === 'local') {
            // Security measure: Deactivate the "wp-force-login" plugin for all sites in non-prod environments
            passthru("$containerExecCommand wp plugin deactivate wp-force-login --url=$domainPath");
        }

        if ($this->target !== 'local' && $this->target !== 'prod') {
            // Security measure: Activate the "wp-force-login" plugin for all sites in non-prod environments
            passthru("$containerExecCommand wp plugin activate wp-force-login --url=$domainPath");
        }
    }

    /**
     * Replaces one S3 bucket name with the target other s3 bucket name.
     *
     * @param string $sourceBucket        The source S3 bucket name.
     * @param string $targetBucket        The target CloudPlatform bucket name.
     * @param string $targetSiteURL       The URL of the target site.
     * @param string $containerExecCommand The command for executing within the container.
     * @return void
     */
    public function stringReplaceS3BucketName($sourceBucket, $targetBucket, $targetSiteURL, $containerExecCommand, $blogID)
    {

        if ($this->blogID !== null) {
            $urlFlag = "--url=$targetSiteURL 'wp_{$blogID}_*' --all-tables-with-prefix";
        } else {
            $urlFlag = "--url=$sourceSiteURL";
        }

        $command = "$containerExecCommand wp search-replace $sourceBucket $targetBucket $urlFlag --network --precise --skip-columns=guid --report-changed-only --recurse-objects";

        echo "[*] Run s3 bucket string replace: $sourceBucket with $targetBucket " . PHP_EOL;

        passthru($command);
    }
}

