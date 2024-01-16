<?php

namespace App\Commands\Db;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use App\Commands\ExportDatabase;

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
        $ExportDatabase = new ExportDatabase();

        $ExportDatabase->runExportMultisite();
        
    }
}