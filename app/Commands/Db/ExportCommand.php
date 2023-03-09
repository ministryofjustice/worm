<?php

namespace App\Commands\Db;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ExportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'db:export {--blogID= : blog id}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Export WP multisite database in .sql file format.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');

        $this->info("Your current namespace: " . $namespace );

        $proceed = $this->ask('Do you wish to proceed?');

        if ( $proceed != 'yes' && $proceed != 'y' ) {
            return;
        }

        # Get current pod name to shell into and run wpcli
        $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));

        $podExec = "kubectl exec -it -c wordpress pod/$podName --";

        $blogID = $this->option('blogID');

        $validBlogID = false;

        if(is_numeric($blogID)){

            # Check Site Exists
            $siteCheck = rtrim(shell_exec("$podExec wp site list --site__in=$blogID --field=blog_id --format=csv"));

            if(empty($siteCheck)){
                $this->info('Site not found');
                return;   
            }

            $validBlogID = true;
        }

        if($validBlogID){

            $sqlFile = $namespace . '-site' . $blogID . '-' . date("Y-m-d-H-i-s") . '.sql'; 

            # Get Single Blog Table Names
            $tableNames = rtrim(shell_exec("$podExec wp db tables 'wp_$blogID*' --all-tables-with-prefix --format=csv"));
        
            if(count(explode(",",$tableNames)) < 10){
                $this->info('Not all blog tables found');
                return;   
            }

            # Export Single Blog from RDS to container
            passthru("$podExec wp db export --porcelain $sqlFile --tables='$tableNames'");

        }
        else {
        
            $sqlFile = $namespace . '-' . date("Y-m-d-H-i-s") . '.sql'; 

            # Export DB from RDS to container
            passthru("$podExec wp db export --porcelain $sqlFile");
        }

        # Copy database from container to local machine
        passthru("kubectl cp $namespace/$podName:$sqlFile $sqlFile -c wordpress");

        # Delete SQL file in container no longer needed
        passthru("$podExec rm $sqlFile");
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
