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
    protected $signature = 'db:export';

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

        $sqlFile = $namespace . '-' . date("Y-m-d-H-i-s") . '.sql'; 

        $this->info("Your current namespace: " . $namespace );

        $proceed = $this->ask('Do you wish to proceed?');

        if ( $proceed != 'yes' ) {
            return;
        }

        # Get current pod name to shell into and run wpcli
        $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));

        # Export DB from RDS to container
        passthru("kubectl exec -it -c wordpress pod/$podName -- wp db export --porcelain $sqlFile");

        # Copy database from container to local machine
        passthru("kubectl cp $namespace/$podName:$sqlFile $sqlFile -c wordpress");
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
