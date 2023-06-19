<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class SwitchCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'switch { target : Environment you are switching to. Options are, prod, staging, dev, demo} ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Switch between different environments.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $target = $this->argument('target');
        $namespace = "hale-platform-$target";
        passthru("kubectl config set-context --current --namespace=$namespace");
        $this->info('Switched to: ' . $namespace);
        passthru("kubectl get all");
    }
}
