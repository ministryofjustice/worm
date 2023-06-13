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
    protected $signature = 'migrate { source : Environment you are migrating from. } { target : Environment you are migrating to. Options are prod, staging, dev, demo. }';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Migrate site(s) from one environment to another, prod, staging, dev, demo';

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

    /**
     * The pod execution command.
     *
     * @var string|null
     */
    protected $podExec;

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
     * The secret name for S3 bucket.
     *
     * @var string|null
     */
    protected $secretName;

    /**
     * The secrets from decode-secret command.
     *
     * @var string|null
     */
    protected $secrets;

    /**
     * The decoded JSON secrets.
     *
     * @var object|null
     */
    protected $json_secrets;

    /**
     * The S3 bucket name.
     *
     * @var string|null
     */
    protected $bucket;

    /**
     * The uploads path.
     *
     * @var string|null
     */
    protected $uploadsPath;

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

/**
 * Execute the console command.
 *
 * @return mixed
 */
public function handle()
{
    $this->source = $this->argument('source');
    $this->target = $this->argument('target');

    // Get source environment namespace
    $this->sourceNamespace = "hale-platform-$this->source";
    $this->sqlFile = $this->sourceNamespace . '-' . date("Y-m-d-H-i-s") . '.sql';
    $this->path = rtrim(shell_exec('pwd'));

    $container = Container::getInstance();
    $this->sites = $container->get('sites');
    $this->profile = "$this->sourceNamespace-s3";

    // $this->uploadsPath = $this->path . "/wordpress/wp-content";
    // $this->oldURL = "$this->sourceNamespace.apps.live.cloud-platform.service.justice.gov.uk";
    // $this->newURL = 'hale.docker';

    // Migrating from CloudPlatform to local Docker site
    if ($this->target === 'local') {
        $this->migrateToLocalEnv(
            $this->source,
            $this->sqlFile,
            $this->target
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
            $this->profile
        );
        return;
    }

    $this->info('Target environment not found.');
}

    /**
     * Migrates the data from the specified source to the local environment.
     *
     * @param string $podExec The pod execution command.
     * @param string $source The source of the database.
     * @param string $sqlFile The SQL file name.
     * @param string $sourceNamespace The source namespace.
     * @param string $podName The pod name.
     * @param string $path The path.
     * @return void
     */
    public function migrateToLocalEnv(
        $source,
        $sqlFile,
        $target
        ) {

        // Check that Docker is running locally
        if (!$this->isDockerRunning()) {
            $this->info("Docker is not running on the local computer.");
        }
            
        // Step 1: Export SQL from RDS to pod container
        $this->exportRdsToContainer($source, $sqlFile) || exit(1);

        // Step 2: Copy SQL file from the container to the local machine
        $this->copyFileFromPod($source, $sqlFile) || exit(1);

        // Step 3: Delete SQL file from the container
        $this->deleteSQLFileContainer($source, $sqlFile) || exit(1);

        // Step 4: Copy SQL file from the local machine to the target container
        $this->copySqlFileToLocalContainer($sqlFile) || exit(1);

        // Step 5: Copy SQL file from container into local MariaDB database
        $this->importDbIntoLocalMariaDb($sqlFile);

        // Step 6: Delete SQL file from the container
        $this->deleteSQLFileLocalContainer($sqlFile) || exit(1);

        // Step 7: Rewrite URLs to match the local hale.docker URL
        $this->replaceDatabaseURLsLocal() || exit(1);

        
        //             // Migration to local specific variables depends on Docker
        // $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));
        // $containerExec = "docker exec -it wordpress";

        // // Export SQL from RDS to pod container
        // $resultCode = $this->exportRdsToContainer($this->podExec, $this->source, $this->sqlFile);

        // if (!$resultCode) {
        //     $this->handleFailure("Failed to export $source database from RDS. Exiting task.");
        //     return;
        // }

        // passthru("kubectl cp -n $sourceNamespace -c wordpress $podName:$sqlFile $sqlFile");

        // # Copy SQL from local machine to Docker container
        // $this->task("🐛 Copying database from local machine to target container.", function () use ($containerID, $sqlFile) {
        //     passthru("docker cp $sqlFile $containerID:/var/www/html/$sqlFile", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // # Import DB into RDS database
        // $this->task("🐛 Importing $source database into local mariadb.", function () use ($containerExec, $sqlFile) {
        //     passthru("$containerExec wp db import $sqlFile", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // # Delete SQL file in container no longer needed
        // $this->task("🐛 Clean-up: delete $source database from container.", function () use ($podExec, $sqlFile) {
        //     passthru("$podExec rm $sqlFile", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // # Delete SQL file on local machine
        // $this->task("🐛 Clean-up: delete sql file on local machine.", function () use ($path, $sqlFile) {
        //     passthru("rm $path/$sqlFile", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // # Perform string replace on imported DB
        // $this->task("🐛 Rewrite database URLs to match target environment.", function () use ($containerExec, $oldURL, $newURL) {
        //     passthru("$containerExec wp search-replace $oldURL $newURL --url=$oldURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // # Check that there is a /wordpress folder in the directory this is run
        // $this->task("🐛 Check /wordpress folder exsits locally.", function () use ($path) {
        //     if (!is_dir($path . "/wordpress")) {
        //         $this->info('Wordpress installation not found. Check you are in the root
        //         directory of the hale-platform repo and have already run
        //         the site locally, so that a wordpress folder has been generated.');
        //         $resultCode = false;
        //     } else {
        //         $resultCode = 0;
        //     }

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

        // $this->task("🐛 Sync s3 $source bucket with local.", function () use ($bucket, $uploadsPath, $profile) {
        //     passthru("aws s3 sync --quiet --profile $profile s3://$bucket $uploadsPath", $resultCode);

        //     $resultCode = ($resultCode === 0) ? true : false;
        //     return $resultCode;
        // });

    }
    
    /**
     * Migrate between CloudPlatform environments.
     *
     * @param string $source The source environment.
     * @param string $sqlFile The SQL file to migrate.
     * @param string $target The target environment.
     * @param array $sites An array of site configurations.
     * @param string $path The path to the SQL file.
     * @param string $profile Your local machines AWS profile config name ie hale-platform-dev-s3.
     * @return void
     */
    public function migrateBetweenCloudPlatformEnv(
        $source,
        $sqlFile,
        $target,
        $sites,
        $path,
        $profile
    ) {
        // Step 1: Export SQL from RDS to pod container
        $this->exportRdsToContainer($source, $sqlFile) || exit(1);

        // Step 2: Copy SQL file from the container to the local machine
        $this->copyFileFromPod($source, $sqlFile) || exit(1);

        // Step 3: Delete SQL file from the container
        $this->deleteSQLFileContainer($source, $sqlFile) || exit(1);

        // Step 4: Copy SQL file from the local machine to the target container
        $this->copySqlFileToContainer($target, $sqlFile) || exit(1);

        // Step 5: Delete the temporary SQL file from the local machine
        $this->deleteSqlFileLocal($path, $sqlFile) || exit(1);

        // Step 6: Import the new database into the target RDS instance
        $this->importContainerToRds($target, $sqlFile) || exit(1);

        // Step 7: Delete SQL file from the target container
        $this->deleteSQLFileContainer($target, $sqlFile) || exit(1);

        // Step 8: Rewrite URLs to match the target environment
        $this->replaceDatabaseURLs($target) || exit(1);

        // Step 9: Perform additional domain rewrite for production source environments
        if (in_array($this->source, ['prod'])) {
            $this->productionDatabaseDomainRewrite($target, $sites) || exit(1);
        }

        // Step 10: Update s3 bucket media assets, docs, images etc with the target environment 
        $this->syncS3BucketWithTarget($source, $target);

        // Migration completed successfully
        $this->info("Success. " . ucfirst($source) . " has been migrated to " . ucfirst($target) . ".");
    }

    /**
     * Get the pod name for the specified namespace.
     *
     * @param string $envName The namespace to get the pod name from.
     * @return string|null The pod name or null if not found.
     */
    private function getPodName($envName)
    {
        $command = "kubectl get pods -n hale-platform-$envName -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'";
        $podName = rtrim(shell_exec($command));

        return $podName ?: null;
    }

    /**
     * Copy file from pod to local machine.
     *
     * @param string $envName The namespace.
     * @param string $sqlFile The SQL file name.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copyFileFromPod($envName, $sqlFile)
    {
        $podName = $this->getPodName($envName);

        return $this->task("🐛 Copying database from container to local machine.", function () use ($envName, $podName, $sqlFile) {
            $command = "kubectl cp --retries=10 -n hale-platform-$envName -c wordpress $podName:$sqlFile $sqlFile";
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
     * @param string $sqlFilePath The local path of the .sql file.
     * @param string $envName The namespace.
     * @param string $sqlFile The destination path in the container.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copySqlFileToContainer($sqlFilePath, $envName, $sqlFile)
    {
        $podName = $this->getPodName($envName);

        $resultCode = $this->task("🐛 Uploading database file from temp local file into $this->target container", function () use ($podName, $sqlFile, $envName, $sqlFilePath) {
            passthru("kubectl cp --retries=10 -n hale-platform-$envName $sqlFilePath hale-platform-$envName/$podName:$sqlFile -c wordpress", $resultCode);
            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        if (!$resultCode) {
            $this->handleFailure("Failed to copy .sql file from local machine to container. Exiting task. Code $resultCode");
            return false;
        }

        return true;
    }

    /**
     * Export the database from RDS to the container.
     *
     * @param string $envName The namespace.
     * @param string $sqlFile The SQL file path.
     * @return bool True if the export is successful; otherwise, false.
     */
    private function exportRdsToContainer($envName, $sqlFile)
    {
        $podExec = $this->getPodExecCommand($envName);

        $resultCode = $this->task("🐛 🐛 Export $envName database from RDS to container", function () use ($podExec, $sqlFile) {
            passthru("$podExec wp db export --porcelain $sqlFile", $resultCode);
            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        if (!$resultCode) {
            $this->handleFailure("Failed to export $source database from RDS. Exiting task.");
            return false;
        }

        return true;
    }

    /**
     * Import the database into RDS.
     *
     * @param string $envName The namespace.
     * @param string $sqlFile The SQL file to import.
     * @return bool True if the import is successful; otherwise, false.
     */
    private function importContainerToRds($envName, $sqlFile)
    {
        $podExec = $this->getPodExecCommand($envName);

        $command = "$podExec wp db import $sqlFile";
        passthru($command, $resultCode);

        if ($resultCode === 0) {
            return true;
        } else {
            $this->handleFailure("Failed to import the database into RDS from target container. Exiting task.");
            return false;
        }
    }

    /**
     * Get the pod execution command.
     *
     * @param string $envName The namespace.
     * @return string The pod execution command.
     */
    private function getPodExecCommand($envName)
    {
        $podName = $this->getPodName($envName);

        return "kubectl exec -it -n hale-platform-$envName -c wordpress pod/$podName --";
    }

    /**
     * Delete the SQL file in the container.
     *
     * @param string $envName The namespace.
     * @param string $sqlFile The SQL file to delete.
     * @return bool Indicates whether the deletion was successful or not.
     */
    private function deleteSQLFileContainer($envName, $sqlFile)
    {
        $podExec = $this->getPodExecCommand($envName);

        return $this->task("🐛 🐛 Delete temp database file from container. No longer needed.", function () use ($podExec, $sqlFile) {
            passthru("$podExec rm $sqlFile", $resultCode);

            if ($resultCode !== 0) {
                $this->handleFailure("Failed to delete $sqlFile from container. Exiting task.");
                return false;
            }

            return true;
        });
    }

    /**
     * Delete the SQL file in the local container.
     *
     * @param string $sqlFile The SQL file to delete.
     * @return bool Indicates whether the deletion was successful or not.
     */
    private function deleteSQLFileLocalContainer($sqlFile)
    {
        return $this->task("🐛 🐛 Delete temp database file from container. No longer needed.", function () use ($sqlFile) {
            passthru("docker exec -it wordpress rm $sqlFile", $resultCode);

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
     */
    private function deleteSqlFileLocal($path, $sqlFile)
    {
        $command = "docker -it wordpresss rm $sqlFile";
        passthru($command, $resultCode);

        if ($resultCode === 0) {
            return true;
        } else {
            $this->handleFailure("Failed to delete SQL file on local machine. Exiting task with error code: $resultCode");
            exit($resultCode);
        }
    }

    /**
     * Replace database URLs to match the target environment.
     *
     * @param string $envName The target environment name.
     * @return bool True if the URL replacement is successful; otherwise, false.
     */
    private function replaceDatabaseURLs($envName)
    {
        // Define the old and new URLs based on the environment names
        $sourceSiteURL = "hale-platform-$this->source.apps.live.cloud-platform.service.justice.gov.uk";
        $targetSiteURL = "hale-platform-$this->target.apps.live.cloud-platform.service.justice.gov.uk";

        // Get the pod execution command for the specified environment
        $podExec = $this->getPodExecCommand($envName);

        // Execute the URL replacement command
        $command = "$podExec wp search-replace $sourceSiteURL $targetSiteURL --url=$sourceSiteURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects";
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
     * Perform site-specific actions for the "prod" environment.
     *
     * @param string $envName The target environment name.
     * @param array $sites The array of site details.
     * @return void
     */
    private function productionDatabaseDomainRewrite($envName, array $sites)
    {
        $podExec = $this->getPodExecCommand($envName);

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['path'];
            $siteID = $site['blogID'];
            $domainPath = "https://hale-platform-$this->source.apps.live.cloud-platform.service.justice.gov.uk/$sitePath";
            $newDomainPath = "hale-platform-$envName.apps.live.cloud-platform.service.justice.gov.uk";

            $this->info($domain);

            passthru("$podExec wp search-replace --url=$domain --network --skip-columns=guid --report-changed-only 'https://$domain' '$domainPath'");
            passthru("$podExec wp db query 'UPDATE wp_blogs SET domain=\"$newDomainPath\" WHERE wp_blogs.blog_id=$siteID'");
            passthru("$podExec wp db query 'UPDATE wp_blogs SET path=\"/$sitePath/\" WHERE wp_blogs.blog_id=$siteID'");

            // Security measure: Activate the "wp-force-login" plugin for all sites in non-prod environments
            passthru("$podExec wp plugin activate wp-force-login --url=$domainPath");
        }
    }

    /**
     * Syncs an S3 bucket with the local machine.
     *
     * This method synchronizes files between two Amazon S3 buckets using the AWS CLI.
     * The source S3 bucket is specified by the `$source` parameter, and the target S3 bucket is specified by the `$target` parameter.
     * The sync operation copies files from the source bucket to the target bucket,
     * ensuring that the target bucket matches the contents of the source bucket.
     *
     * @param string $source The source environment representing the name of the source S3 bucket.
     * @param string $target The target environment representing the name of the target S3 bucket.
     */
    public function syncS3BucketWithTarget($source, $target)
    {
        $resultCode = 0;
        $output = '';

        $sourceBucketsecretName = $this->getSecretName($source);
        $sourceBucketsecrets = $this->decodeSecrets($source);

        $targetBucketsecretName = $this->getSecretName($target);
        $targetBucketsecrets = $this->decodeSecrets($target);

        $sourceBucketjson_secrets = json_decode($sourceBucketsecrets, true);
        $sourceBucket = $sourceBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        $targetBucketjson_secrets = json_decode($targetBucketsecrets, true);
        $targetBucket = $targetBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        passthru("aws s3 sync s3://$sourceBucket/uploads s3://$targetBucket/uploads --profile hale-platform-$source-s3 --profile hale-platform-$target-s3 --acl=public-read");
    }

    /**
     * Get the secret name.
     *
     * @param string $envName The environment name.
     * @return string The secret name.
     */
    private function getSecretName($envName)
    {
        $podName = $this->getPodName($envName);

        $command = "kubectl describe -n hale-platform-$envName pods/$podName | grep -o 'wpsecrets-[[:digit:]]*'";
        $output = shell_exec($command);

        return rtrim($output);
    }

    /**
     * Decode the secrets.
     *
     * @param string $envName The environment name.
     * @return string The decoded secrets.
     */
    private function decodeSecrets($envName)
    {
        $this->secretName = $this->getSecretName($envName);

        $command = "cloud-platform decode-secret -n hale-platform-$envName -s $this->secretName";
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
     * @param string $containerID The ID of the Docker container.
     * @param string $sqlFile The SQL file to copy.
     * @return bool True if the copy is successful; otherwise, false.
     */
    private function copySqlFileToLocalContainer($sqlFile)
    {
        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));

        $resultCode = $this->task("🐛 🐛 Copying database from local machine to target container.", function () use ($containerID, $sqlFile) {
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
     *
     * @param string $containerExec The container execution command.
     * @param string $oldURL The old URL to be replaced.
     * @param string $newURL The new URL to replace with.
     * @return bool True if the string replace is successful; otherwise, false.
     */
    private function replaceDatabaseURLsLocal()
    {

        // Define the old and new URLs based on the environment names
        $sourceSiteURL = "hale-platform-$this->source.apps.live.cloud-platform.service.justice.gov.uk";
        $targetSiteURL = "hale.docker";

        $command = "docker exec -it wordpress wp search-replace $sourceSiteURL $targetSiteURL --url=$sourceSiteURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects";

        // Execute the command and capture the result code
        passthru($command, $resultCode);

        $resultCode = ($resultCode === 0) ? true : false;
        return $resultCode;
    }


}
