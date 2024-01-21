<?php

namespace App\Commands\Db;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Commands\ImportDatabase;

class ImportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */

    protected $signature = "db:import
        { target : Target environment you are importing DB to, ie prod, staging, dev, demo, local. }
        { file : Path to the database file you want to import. }
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

        $target = $this->argument('target');
        $sqlFile = $this->argument('file');
        $blogID = $this->option('blogID');

        $sqlFile = basename($sqlFile);

        var_dump($sqlFile); die();

        $importDatabaseObject = new ImportDatabase($target, $blogID = null, $path = "." );

        $importDatabaseObject->runImportMultisite();

    }
}
