<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class JumpCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'jump { target : Enter environment name you want to shell into, prod, staging, dev etc}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Shell into the first pod available of any given environment.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $target = $this->argument('target');
        $podName = $this->getPodName($target);

        // Warn when jumping to prod
        if ($target === 'prod') {
            // Force manual approval prompt you are migrating to prod
            $proceed = $this->ask("### WARNING ### You are jumping into the prod container. Do you wish to proceed? [n/y]");

            // If not "yes", then exit.
            if ($proceed != 'yes' && $proceed != 'y') {
                $this->info("Jump to prod canceled. Exiting task.");
                exit(0);
            }
        }

        # Export DB from RDS to container
        passthru("kubectl exec -it -n hale-platform-$target -c wordpress pod/$podName -- bash");
    }

    /**
     * Get the pod name for the specified namespace.
     *
     * @param string $envName The namespace to get the pod name from.
     * @return string|null The pod name or null if not found.
     */
    private function getPodName($envName)
    {
        $command = "kubectl get pods -n hale-platform-$envName -o=name | grep -m 1 wordpress | sed 's/^.\{4\}//'";
        $podName = rtrim(shell_exec($command));

        return $podName ?: null;
    }
}
