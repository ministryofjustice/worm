<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class SitesCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'sites';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List all sites in the multisite installation.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
         # Get current pod name to shell into and run wpcli
         $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));

         # Export DB from RDS to container
         passthru("kubectl exec -it -c wordpress pod/$podName -- wp site list --fields=blog_id,url");
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
