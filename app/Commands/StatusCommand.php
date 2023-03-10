<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class StatusCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'status {--secrets : print out namespace wpsecrets}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display current terminal connection details to k8s cluster.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');
        $this->info("Your current namespace: " . $namespace);

        if ($this->option('secrets')) {
            $podName = rtrim(shell_exec('kubectl get pods -o=name | grep -m 1 wordpress | sed "s/^.\{4\}//"'));

            # Current k8s secret name
            $secretName = rtrim(shell_exec("kubectl describe pods/$podName | grep -oP 'wpsecrets-\d\d'"));

            $secrets = shell_exec("cloud-platform decode-secret -n $namespace -s $secretName");
            $this->info($secrets);
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
