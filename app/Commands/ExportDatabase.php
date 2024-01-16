<?php

namespace App\Commands;

use App\Helpers\Kubernetes;

class ExportDatabase 
{

    protected $target;
    protected $blogID = null;
    protected $podName;
    protected $containerExec;
    protected $sqlFile;
    protected $kubernetes;

    public function __construct($env, $sqlFile, $blogID = null) {
        $this->blogID = $blogID;
        $this->sqlFile = $sqlFile;

        $this->kubernetes = new Kubernetes($env);
        $this->podName = $this->kubernetes->getPodName($env,"wordpress");
        $this->containerExec = $this->kubernetes->getExecCommand($env);
    }    

    public function runExportMultisite() {
        var_dump("$this->containerExec wp db export --porcelain $this->sqlFile");
    }

    public function runExportSingleSite($blogID) {

        $validBlogID = false;

        if (is_numeric($blogID)) {
            # Check Site Exists
            $siteCheck = rtrim(shell_exec("$podExec wp site list --site__in=$blogID --field=blog_id --format=csv"));

            if (empty($siteCheck)) {
                echo'Site not found';
                return;
            }

            $validBlogID = true;
        }

        if ($validBlogID) {
            $sqlFile = EnvSet::GenerateFileName('staging', '3');

            # Get Single Blog Table Names
            $tableNames = rtrim(shell_exec("$podExec wp db tables 'wp_{$blogID}*' --all-tables-with-prefix --format=csv"));

            if (count(explode(",", $tableNames)) < 10) {
                $this->info('Not all blog tables found');
                return;
            }

            # Export Single Blog from RDS to container
            passthru("$podExec wp db export --porcelain $sqlFile --tables='$tableNames'");
        } else {
            $sqlFile = $namespace . '-' . date("Y-m-d-H-i-s") . '.sql';

            # Export DB from RDS to container
            passthru("$podExec wp db export --porcelain $sqlFile");
        }

        # Copy database from container to local machine
        passthru("kubectl cp $namespace/$podName:$sqlFile $sqlFile -c wordpress");

        # Delete SQL file in container no longer needed
        passthru("$podExec rm $sqlFile");


    }
}