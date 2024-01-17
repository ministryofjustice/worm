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
    protected $signature = 'db:export 
                            {--blogID= : blog id}
                            { target : Target environment you are exporting DB from }';

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
        $blogID = $this->option('blogID');
        $target = $this->argument('target');
        $sqlfile = 'wordpress';

        $ExportDatabase = new ExportDatabase($target, $sqlfile , $blogID = null);

        $ExportDatabase->runExportMultisite();
        
    }
}