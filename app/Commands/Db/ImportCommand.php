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

        
    }
}
