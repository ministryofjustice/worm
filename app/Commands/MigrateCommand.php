<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

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
     * The namespace.
     *
     * @var string|null
     */
    protected $namespace;

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
     * Source environment domain URL.
     *
     * @var string|null
     */
    protected $oldURL;

    /**
     * Target domain URL.
     *
     * @var string|null
     */
    protected $newURL;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->source = $this->argument('source');
        $this->target = $this->argument('target');

        $this->namespace = "hale-platform-$this->source";
        $this->podName = rtrim(shell_exec("kubectl get pods -n $this->namespace -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'"));
        $this->podExec = "kubectl exec -it -n $this->namespace -c wordpress pod/$this->podName --";
        $this->sqlFile = 'hale-platform-' . $this->source . '-' . date("Y-m-d-H-i-s") . '.sql';
        $this->path = rtrim(shell_exec('pwd'));

        $this->secretName = rtrim(shell_exec("kubectl describe -n $this->namespace pods/$this->podName | grep -o 'wpsecrets-[[:digit:]]*'"));
        $this->secrets = shell_exec("cloud-platform decode-secret -n $this->namespace -s $this->secretName");
        $this->json_secrets = json_decode($this->secrets);
        $this->bucket = $this->json_secrets->data->S3_UPLOADS_BUCKET;
        $this->uploadsPath = $this->path . "/wordpress/wp-content";
        $this->profile = "$this->namespace-s3";
        $this->oldURL = "$this->namespace.apps.live.cloud-platform.service.justice.gov.uk";
        $this->newURL = 'hale.docker';

        // Migrating from CloudPlatform to local Docker site
        if ($this->target === 'local') {
            $this->migrateToLocalEnv(
                $this->podExec,
                $this->source,
                $this->sqlFile,
                $this->namespace,
                $this->podName,
                $this->path,
                $this->target,
                $this->secretName,
                $this->secrets,
                $this->json_secrets,
                $this->bucket,
                $this->uploadsPath,
                $this->profile,
                $this->oldURL,
                $this->newURL
            );
            return;
        }

        // Migrating between CloudPlatform environments
        if (in_array($this->target, ['prod', 'staging', 'dev', 'demo'])) {
            $this->migrateBetweenCloudPlatformEnv(
                $this->podExec,
                $this->source,
                $this->sqlFile,
                $this->namespace,
                $this->podName,
                $this->path,
                $this->target,
                $this->secretName,
                $this->secrets,
                $this->json_secrets,
                $this->bucket,
                $this->uploadsPath,
                $this->profile,
                $this->oldURL,
                $this->newURL
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
         * @param string $namespace The namespace.
         * @param string $podName The pod name.
         * @param string $path The path.
         * @return void
         */
        public function migrateToLocalEnv(
            $podExec,
            $source,
            $sqlFile,
            $namespace,
            $podName,
            $path,
            $target,
            $secretName,
            $secrets,
            $json_secrets,
            $bucket,
            $uploadsPath,
            $profile,
            $oldURL,
            $newURL
        ) {
        
        // Migration to local specific variables depends on Docker
        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));
        $containerExec = "docker exec -it wordpress";

        // Export SQL from RDS to pod container
        $resultCode = $this->exportRdsToContainer($this->podExec, $this->source, $this->sqlFile);

        if (!$resultCode) {
            $this->handleFailure("Failed to export $source database from RDS. Exiting task.");
            return;
        }

        passthru("kubectl cp -n $namespace -c wordpress $podName:$sqlFile $sqlFile");

        # Copy SQL from local machine to container
        $this->task("Copying database from local machine to target container.", function () use ($containerID, $sqlFile) {
            passthru("docker cp $sqlFile $containerID:/var/www/html/$sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        # Import DB into RDS database
        $this->task("Importing $source database into local mariadb.", function () use ($containerExec, $sqlFile) {
            passthru("$containerExec wp db import $sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        # Delete SQL file in container no longer needed
        $this->task("Clean-up: delete $source database from container.", function () use ($podExec, $sqlFile) {
            passthru("$podExec rm $sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        # Delete SQL file on local machine
        $this->task("Clean-up: delete sql file on local machine.", function () use ($path, $sqlFile) {
            passthru("rm $path/$sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        # Perform string replace on imported DB
        $this->task("Rewrite database URLs to match target environment.", function () use ($containerExec, $oldURL, $newURL) {
            passthru("$containerExec wp search-replace $oldURL $newURL --url=$oldURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        # Check that there is a /wordpress folder in the directory this is run
        $this->task("Check /wordpress folder exsits locally.", function () use ($path) {
            if (!is_dir($path . "/wordpress")) {
                $this->info('Wordpress installation not found. Check you are in the root
                directory of the hale-platform repo and have already run
                the site locally, so that a wordpress folder has been generated.');
                $resultCode = false;
            } else {
                $resultCode = 0;
            }

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        $this->task("Sync s3 $source bucket with local.", function () use ($bucket, $uploadsPath, $profile) {
            passthru("aws s3 sync --quiet --profile $profile s3://$bucket $uploadsPath", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });
    }

    /**
     * Migrate between CloudPlatform environments
     *
     * @param string $podExec
     * @param string $source
     * @param string $sqlFile
     * @param string $namespace
     * @param string $podName
     * @param string $path
     * @param string $target
     * @param string $secretName
     * @param string $secrets
     * @param object $json_secrets
     * @param string $bucket
     * @param string $uploadsPath
     * @param string $profile
     * @return void
     */
    public function migrateBetweenCloudPlatformEnv(
        $podExec,
        $source,
        $sqlFile,
        $namespace,
        $podName,
        $path,
        $target,
        $secretName,
        $secrets,
        $json_secrets,
        $bucket,
        $uploadsPath,
        $profile,
        $oldURL,
        $newURL
    ) {

        // Export SQL from RDS to pod container
        $resultCode = $this->exportRdsToContainer($this->podExec, $this->source, $this->sqlFile);

        if (!$resultCode) {
            $this->handleFailure("Failed to export $source database from RDS. Exiting task.");
            return;
        }

        echo "Migrating from $source to $target.";
    }

    /**
     * Handle the failure by displaying an error message and exiting the task.
     *
     * @param string $errorMessage
     * @return void
     */
    private function handleFailure($errorMessage)
    {
        $this->info($errorMessage);
    }

    /**
     * Export the database from RDS to the container.
     *
     * @param string $podExec   The pod exec command.
     * @param string $source    The source database.
     * @param string $sqlFile   The SQL file path.
     * @return bool             True if the export is successful; otherwise, false.
     */
    private function exportRdsToContainer($podExec, $source, $sqlFile)
    {
        $resultCode = $this->task("Export $source database from RDS", function () use ($podExec, $sqlFile) {
            passthru("$podExec wp db export --porcelain $sqlFile", $resultCode);
            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        return $resultCode;
    }

}
