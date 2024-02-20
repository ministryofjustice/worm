<?php

namespace App\Commands;

use App\Helpers\Kubernetes;
use App\Helpers\Wordpress;

/**
 * Class ImportDatabase
 *
 * Represents a utility for importing WordPress multisite databases within a Kubernetes environment.
 *
 * @package App\Commands
 */
class ImportDatabase
{
    /**
     * The target environment for the database import operation.
     *
     * @var string
     */
    protected $target;


    /**
     * The source.
     *
     * @var string
     */
    protected $source;

    /**
     * The file path of the SQL file to be imported.
     *
     * @var string
     */
    protected $filePath;

    /**
     * The name of the SQL file to be imported.
     *
     * @var string
     */
    protected $fileName;

    /**
     * Optional blog ID for single-site import. Default is null.
     *
     * @var int|null
     */
    protected $blogID;

    /**
     * Optional S3 synchronization flag. Default is null.
     *
     * @var string|null
     */
    protected $s3sync;

    /**
     * The name of the Kubernetes pod where the WordPress instance resides.
     *
     * @var string
     */
    protected $podName;

    /**
     * The command used for executing commands within a Kubernetes container.
     *
     * @var string
     */
    protected $containerExec;

    /**
     * An instance of the Kubernetes helper class.
     *
     * @var Kubernetes
     */
    protected $kubernetesObject;

    /**
     * An instance of the WordPress helper class.
     *
     * @var Wordpress
     */
    protected $wordpressObject;

    /**
     * Constructor for the ImportDatabase class.
     *
     * Initializes an instance of the ImportDatabase class with the provided parameters, such as the target environment,
     * file path, file name, an optional blog ID, and an optional S3 synchronization flag.
     * This constructor sets up Kubernetes-related properties, including the pod name and container execution command,
     * and initializes instances of the Kubernetes and WordPress helper classes for subsequent import operations.
     *
     * @param string $target   The target environment for the import.
     * @param string $filePath The path to the SQL file to be imported.
     * @param string $fileName The name of the SQL file to be imported.
     * @param int|null $blogID Optional blog ID for single-site import. Default is null.
     * @param string|null $s3sync Optional S3 synchronization flag. Default is null.
     */
    public function __construct($target, $source, $filePath, $fileName, $blogID = null, $s3sync = null)
    {
        $this->target = $target;
        $this->source = $source;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->blogID = $blogID;
        $this->s3sync = $s3sync;
        $this->kubernetesObject = new Kubernetes();
        $this->wordpressObject = new Wordpress();
        $this->containerExec = $this->kubernetesObject->getExecCommand($target);
        $this->podName = $this->kubernetesObject->getPodName($target, "wordpress");
    }

    /**
     * Execute the WordPress multisite database import operation.
     *
     * This method orchestrates the database import process.
     * It copies the SQL file into the Kubernetes container, executes the 'wp db import' command,
     * removes the imported SQL file, performs URL replacements in the database, and optionally synchronizes S3 buckets
     * and performs string replacements of S3 bucket names in the WordPress installation.
     */
    public function runDatabaseImport()
    {
        $this->copyDatabaseToContainer();
        $this->executeDbImportCommand();
        $this->removeSqlFileFromContainer();
        $this->replaceDatabaseURLs();

        if ($this->s3sync === 'true') {
            $this->syncS3Buckets();
            $this->replaceS3BucketNames();
        }
    }

    /**
     * Copy the SQL file into the Kubernetes container.
     */
    protected function copyDatabaseToContainer()
    {
        $this->kubernetesObject
            ->copyDatabaseToContainer($this->target, $this->filePath, $this->fileName, $this->podName);
    }

    /**
     * Execute the 'wp db import' command in the Kubernetes container.
     */
    protected function executeDbImportCommand()
    {
        $importCommand = "$this->containerExec wp db import $this->fileName";
        passthru($importCommand);
    }

    /**
     * Remove the imported SQL file from the Kubernetes container.
     */
    protected function removeSqlFileFromContainer()
    {
        $removeCommand = "$this->containerExec rm $this->fileName";
        passthru($removeCommand);
    }

    /**
     * Perform URL replacements in the WordPress database.
     */
    protected function replaceDatabaseURLs()
    {
        $this->wordpressObject->replaceDatabaseURLs($this->target, $this->source, $this->blogID);
    }

    /**
     * Sync S3 buckets between source and target environments.
     */
    protected function syncS3Buckets()
    {
        $this->kubernetesObject->syncS3Buckets($this->target, $this->source, $this->blogID);
    }

    /**
     * Perform string replacement of S3 bucket names in the WordPress installation.
     */
    protected function replaceS3BucketNames()
    {
        $targetBucket = $this->kubernetesObject->getBucketName($this->target);
        $sourceBucket = $this->kubernetesObject->getBucketName($this->source);
        $this->wordpressObject->stringReplaceS3BucketName($targetBucket, $sourceBucket, $this->blogID);
    }
}
