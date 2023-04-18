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
    protected $signature = 'migrate { source : Environment you are migrating from. } { target : Environment you are copying to. }';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Migrate to the local environment';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $source = $this->argument('source');
        $target = $this->argument('target');

        $namespace = "hale-platform-$source";
        $podName = rtrim(shell_exec("kubectl get pods -n $namespace -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'"));
        $podExec = "kubectl exec -it -n $namespace -c wordpress pod/$podName --";
        $sqlFile = 'hale-platform-' . $source . '-' . date("Y-m-d-H-i-s") . '.sql';
        $path = rtrim(shell_exec('pwd'));

        # Export DB from RDS to container
        $this->task("Export $source database from RDS", function () use ($podExec, $sqlFile) {
            passthru("$podExec wp db export --porcelain $sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        $this->task("Copying database from remote container to local machine.", function () use ($namespace, $podName, $sqlFile) {
            passthru("kubectl cp -n $namespace -c wordpress $podName:$sqlFile $sqlFile", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });

        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));
        $containerExec = "docker exec -it wordpress";

        # Get URLs to run WP find and replace on database
        $oldURL = "$namespace.apps.live.cloud-platform.service.justice.gov.uk";
        $newURL = 'hale.docker';

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

        $secretName = rtrim(shell_exec("kubectl describe -n $namespace pods/$podName | grep -o 'wpsecrets-[[:digit:]]*'"));
        $secrets = shell_exec("cloud-platform decode-secret -n $namespace -s $secretName");
        $json_secrets = json_decode($secrets);
        $bucket = $json_secrets->data->S3_UPLOADS_BUCKET;
        $uploadsPath = $path . "/wordpress/wp-content";
        $profile = "$namespace-s3";

        $this->task("Sync s3 $source bucket with local.", function () use ($bucket, $uploadsPath, $profile) {
            passthru("aws s3 sync --quiet --profile $profile s3://$bucket $uploadsPath", $resultCode);

            $resultCode = ($resultCode === 0) ? true : false;
            return $resultCode;
        });
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
