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

    protected $signature = "db:import
        { target : Target environment you are importing DB to, ie prod, staging, dev, demo, local. }
        { path : Path of SQL file to import. }
        {--blogID= : Blog id of remote site db you want to replace. }";

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Import WP multisite database(s) in .sql file format.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        # Get current pod name to shell into and run wpcli
        $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));
        $podExec = "kubectl exec -it -c wordpress pod/$podName --";
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');
        $blogID = $this->option('blogID');
        $target = $this->argument('target');
        $sqlFilePath = $this->argument('path');
        $sqlFile = basename($sqlFilePath);
        $containerID = rtrim(shell_exec('docker ps -aqf "name=^wordpress$"'));
        $containerExec = "docker exec -it wordpress";

        # Version controlled record of our multisite domains matched to blog ID
        $sites = array(
            "mag" => [
                "blogID" => 3,
                "domain" => "magistrates.judiciary.uk",
                "path" => "magistrates",
            ],

            "ccr" => [
                "blogID" => 5,
                "domain" => "ccrc.gov.uk",
                "path" => "ccrc",
            ],

            "vic" => [
                "blogID" => 6,
                "domain" => "victimscommissioner.org.uk",
                "path" => "vc",
            ],

            "cym" => [
                "blogID" => 11,
                "domain" => "magistrates.judiciary.uk/cymraeg",
                "path" => "cymraeg",
            ],

            "pds" => [
                "blogID" => 12,
                "domain" => "publicdefenderservice.org.uk",
                "path" => "pds",
            ],

            "imb" => [
                "blogID" => 13,
                "domain" => "imb.org.uk",
                "path" => "imb",
            ],

            "icr" => [
                "blogID" => 16,
                "domain" => "icrir.independent-inquiry.uk",
                "path" => "icrir",
            ],
        );

        # Check we have an sql file before we even get going
        if (!file_exists($sqlFilePath)) {
            $this->info('SQL file not found.');
            return;
        }

        if ($target === 'local') {
            $this->importToLocalEnv($containerID, $containerExec, $blogID, $sqlFilePath, $sqlFile, $sites);
            return;
        }

        if ($target === 'prod' || $target === 'staging' || $target === 'dev' || $target === 'demo') {
            $this->importToCloudPlatformEnv($podName, $podExec, $namespace, $blogID, $sqlFilePath, $sqlFile, $target, $sites);
            return;
        }

        $this->info('You need to choose either, prod, staging, dev, demo or local as the 3 argument.');
        return;
    }

    /**
     * Import DB to local Docker instance of the multisite
     *
     * @return mixed
     */
    public function importToLocalEnv($containerID, $containerExec, $blogID, $sqlFilePath, $sqlFile, $sites)
    {
        # Remove once we add in support for single site import
        if ($blogID != null) {
            $this->info('WORM currently does not support importing single site to local db.');
            return;
        }

        $confirmInfo = 'You are currently targeting the local environment.
            Make sure you have the site running locally before continuing.
            Do you wish to proceed? [y/n]';

        # Confirm that the person is in the right namespace
        $proceed = $this->ask($confirmInfo);

        # Prompt to proceed
        if ($proceed != 'yes' && $proceed != 'y') {
            return;
        }

        $this->info('What is the URL of the database you are importing? ie, jotwpublic.prod.wp.dsd.io');

        # Get URLs to run WP find and replace on database
        $oldURL = rtrim($this->ask('Old URL:'));
        $newURL = 'hale.docker';

        # Copy SQL from local machine to container
        $this->info('Copying .sql file from local into target container ...');
        passthru("docker cp $sqlFilePath $containerID:/var/www/html/$sqlFile");

        # Import DB into RDS database
        $this->info('Importing .sql database into local mariadb ...');
        passthru("$containerExec wp db import $sqlFile");

        $this->info('Clean up and remove sql file from container ...');
        # Delete SQL file in container no longer needed
        passthru("$containerExec rm $sqlFile");

        # Perform string replace on imported DB
            $this->info('Replacing database URLs to match target environment ...');
        passthru("$containerExec wp search-replace $oldURL $newURL --url=$oldURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects");

        # Continue further URL rewriting if we are going from prod to all other
        # environments that don't use domains
        $this->info('Perform find and replace on domains to convert to WP paths ...');

        $this->info('Runing search and replace on:');

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['path'];
            $siteID = $site['blogID'];
            $domainPath = "https://hale.docker/$sitePath";
            $newDomainPath = "hale.docker";

            $this->info($domain);

            passthru("$containerExec wp search-replace --url=$domain --network --skip-columns=guid --report-changed-only 'https://$domain' '$domainPath'");
            passthru("$containerExec wp db query 'UPDATE wp_blogs SET domain=\"$newDomainPath\" WHERE wp_blogs.blog_id=$siteID'");
            passthru("$containerExec wp db query 'UPDATE wp_blogs SET path=\"/$sitePath/\" WHERE wp_blogs.blog_id=$siteID'");

            // Disable security measure from db as in local environment
            // enabled in the dev environments
            passthru("$containerExec wp plugin deactivate wp-force-login --url=$domainPath");
        }
        $this->info('Import script finished.');
    }

    /**
     * Import DB to an environment in CloudPlatform
     *
     * @return mixed
     */
    public function importToCloudPlatformEnv($podName, $podExec, $namespace, $blogID, $sqlFilePath, $sqlFile, $target, $sites)
    {
        # Confirm that the person is in the right namespace
        $proceed = $this->ask("Your current namespace is $namespace. Do you wish to proceed?");

        # Prompt to proceed
        if ($proceed != 'yes' && $proceed != 'y') {
            return;
        }

        # Apply checks if user inputs a blog id for a single site import
        if ($blogID != null) {
            $this->info('Checking local file and remote blog match blog id entered.');

            $siteCheckLocal = rtrim(shell_exec("cat $sqlFilePath | grep wp_'$blogID'_commentmeta"));
            $siteCheckRemote = rtrim(shell_exec("$podExec wp site list --site__in=$blogID --field=blog_id --format=csv"));

            # Should return data otherwise grep has found nothing
            if (!strlen($siteCheckLocal) > 0) {
                $this->info('Error, the database file and blog id param you provided
                    do not have matching blog ids.');
                return;
            };

            # Match the remote blog id with the one entered
            if ($siteCheckRemote != $blogID) {
                $this->info('The blogID you entered does not exist on the remote site.
                    Create the site first and then run the db import into it.');
                return;
            };
        }

        $this->info('Get URLs to run WP find & replace on imported database:');

        # Get URLs to run WP find and replace on database
        $oldURL = rtrim($this->ask('Old URL:'));
        $newURL = rtrim($this->ask('New URL:'));

        $this->info('Get s3 bucket names to find and replace:');

        $olds3Bucket = rtrim($this->ask('Old s3 bucket name:'));
        $news3Bucket = rtrim($this->ask('New s3 bucket name:'));

        $urlsMatch = false;

        if ($oldURL === $newURL) {
            $this->info('URLs match, will not run WP URL search and replace on db.');
            $urlsMatch = true;
        }

        if (strlen($oldURL) == 0) {
            $this->info('Old URL incorrect or not found.');
            return;
        }

        if (strlen($newURL) == 0) {
            $this->info('New URL incorrect or not found.');
            return;
        }

        # Copy SQL from local machine to container
        $this->info('Copying .sql file from local into target container ...');
        passthru("kubectl cp $sqlFilePath $namespace/$podName:$sqlFile -c wordpress");

        # Import DB into RDS database
        $this->info('Importing .sql database into RDS ...');
        passthru("$podExec wp db import $sqlFile");

        $this->info('Clean up and remove sql file from container ...');
        # Delete SQL file in container no longer needed
        passthru("$podExec rm $sqlFile");

        # Perform string replace on imported DB
        if ($urlsMatch != true) {
            $this->info('Replacing database URLs to match target environment ...');
            passthru("$podExec wp search-replace $oldURL $newURL --url=$oldURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects");
        }

        # s3 bucket find and replace
        $this->info('Replace s3 bucket name with target CloudPlatform bucket name ...');
        passthru("$podExec wp search-replace $olds3Bucket $news3Bucket --url=$newURL --network --precise --skip-columns=guid --report-changed-only --recurse-objects");

        # We may want to skip rewriting the domains
        $proceed = $this->ask('You are importing to production, do you want to keep the domains intact and skip rewriting them? [y/n]');

        if ($proceed == 'yes' || $proceed == 'y') {
            $this->info('DB import complete.');
            return;
        }

        # Continue further URL rewriting if we are going from prod to all other
        # environments that don't use domains
        $this->info('Perform find and replace on domains to convert to WP paths ...');

        $this->info('Runing search and replace on:');

        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['path'];
            $siteID = $site['blogID'];
            $domainPath = "https://$namespace.apps.live.cloud-platform.service.justice.gov.uk/$sitePath";
            $newDomainPath = "$namespace.apps.live.cloud-platform.service.justice.gov.uk";

            $this->info($domain);

            passthru("$podExec wp search-replace --url=$domain --network --skip-columns=guid --report-changed-only 'https://$domain' '$domainPath'");
            passthru("$podExec wp db query 'UPDATE wp_blogs SET domain=\"$newDomainPath\" WHERE wp_blogs.blog_id=$siteID'");
            passthru("$podExec wp db query 'UPDATE wp_blogs SET path=\"/$sitePath/\" WHERE wp_blogs.blog_id=$siteID'");

            // Security measure db copy from production make sure all sites have force login
            // enabled in the dev environments
            passthru("$podExec wp plugin activate wp-force-login --url=$domainPath");
        }
        $this->info('Import script finished.');
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
