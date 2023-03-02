<?php

namespace App\Commands\Db;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ImportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'db:import { path : Path of sql file to import. }';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Import WP multisite database in .sql file format.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        # Path entered in by user '/user/wp.sql'
        $sqlFilePath = $this->argument('path');

        # Generate file name without path 
        $sqlFile = basename($this->argument('path'));

        # Get current namespace in k8s cluster
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');

        $this->info("Your current namespace: " . $namespace );

        $proceed = $this->ask('Do you wish to proceed? [yes|no]');

        if ( $proceed != 'yes' ) {
            return;
        }

        # Get URLs to run WP find and replace on database
        $oldURL = $this->ask('Old URL:');
        $newURL = $this->ask('New URL:');

        $urlsMatch = false;

        if ($oldURL === $newURL) {
            $this->info('URLs match, will not run WP URL search and replace on db.');
            $urlsMatch = true;

        }

        if (!file_exists($sqlFilePath)) {
            $this->info('File not found.');
            return;
        }

        if (strlen($oldURL) == 0) {
            $this->info('Old URL incorrect or not found.');
            return;
        }

        if (strlen($newURL) == 0) {
            $this->info('New URL incorrect or not found.');
            return;
        }

        # Retreive a pod name currently running WordPress 
        $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));

        # Copy SQL from local machine to container
        passthru("kubectl cp $sqlFilePath $namespace/$podName:$sqlFile -c wordpress");

        # Import DB into RDS database
        passthru("kubectl exec -it -c wordpress pod/$podName -- wp db import $sqlFile");

        # Delete SQL file in container no longer needed
        passthru("kubectl exec -it -c wordpress pod/$podName -- rm $sqlFile");

        # Perform string replace on imported DB
        if ($urlsMatch != true) { 
            passthru("kubectl exec -it -c wordpress pod/$podName -- wp search-replace $oldURL $newURL --url=$oldURL --network");
        }
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
