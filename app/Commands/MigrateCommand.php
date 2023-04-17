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

        $podName = rtrim(shell_exec("kubectl get pods -n hale-platform-$source -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'"));
        $podExec = "kubectl exec -it -n hale-platform-$source -c wordpress pod/$podName --";
        $sqlFile = 'hale-platform-' . $source . '-' . date("Y-m-d-H-i-s") . '.sql';

        # Export DB from RDS to container
        $this->task("Export $source database from RDS", function () use ($podExec, $sqlFile) {
            passthru("$podExec wp db export --porcelain $sqlFile");
            return true;
        });

        $this->task("Copy database from remote container to local.", function () use ($source, $podName, $sqlFile) {
            passthru("kubectl cp -n hale-platform-$source -c wordpress $podName:$sqlFile $sqlFile");
            return true;
        });

        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));
        $containerExec = "docker exec -it wordpress";

        # Get URLs to run WP find and replace on database
        $oldURL = "hale-platform-$source.apps.live.cloud-platform.service.justice.gov.uk";
        $newURL = 'hale.docker';

        # Copy SQL from local machine to container
        $this->task("Copying .sql file from local into target container.", function () use ($containerID, $sqlFile) {
            passthru("docker cp $sqlFile $containerID:/var/www/html/$sqlFile");
            return true;
        });

        # Import DB into RDS database
        $this->task("Importing .sql database into local mariadb.", function () use ($containerExec, $sqlFile) {
            passthru("$containerExec wp db import $sqlFile");
            return true;
        });

        # Delete SQL file in container no longer needed
        $this->task("Clean-up task: Delete $source database from container.", function () use ($podExec, $sqlFile) {
            passthru("$podExec rm $sqlFile");
            return true;
        });

        # Perform string replace on imported DB
        $this->task("Rewrite database URLs to match target environment.", function () use ($containerExec, $oldURL, $newURL) {
            passthru("$containerExec wp search-replace $oldURL $newURL --url=$oldURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects");
            return true;
        });

        $path = rtrim(shell_exec('pwd'));

        # Check that there is a /wordpress folder in the directory this is run
        $this->task("Check /wordpress folder exsits locally.", function () use ($path) {
            if (!is_dir($path . "/wordpress")) {
                $this->info('Wordpress installation not found. Check you are in the root
                directory of the hale-platform repo and have already run
                the site locally, so that a wordpress folder has been generated.');
                return;
            }
        });

        $secretName = rtrim(shell_exec("kubectl describe -n hale-platform-$source pods/$podName | grep -o 'wpsecrets-[[:digit:]]*'"));
        $secrets = shell_exec("cloud-platform decode-secret -n hale-platform-$source -s $secretName");
        $json_secrets = json_decode($secrets);
        $bucket = $json_secrets->data->S3_UPLOADS_BUCKET;
        $uploadsPath = $path . "/wordpress/wp-content";
        $profile = "hale-platform-$source-s3";

        $this->task("Sync s3 media bucket with $source.", function () use ($bucket, $uploadsPath, $profile) {
            passthru("aws s3 sync --quiet --profile $profile s3://$bucket $uploadsPath");
            return true;
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
