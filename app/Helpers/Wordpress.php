<?php

namespace App\Helpers;

use App\Helpers\Kubernetes;
use App\Helpers\EnvSet;

class Wordpress
{
    /**
     * The Kubernetes object used for interacting with Kubernetes resources.
     *
     * @var Kubernetes
     */
    protected $kubernetesObject;

    /**
     * The EnvSet object used for managing environment settings.
     *
     * @var EnvSet
     */
    protected $envSetObject;

    /**
     * Constructor method for the ImportCommand class.
     *
     * Initializes the Kubernetes and EnvSet objects used within the ImportCommand class.
     * These objects are responsible for interacting with Kubernetes resources and environment settings, respectively.
     *
     * @return void
     */
    public function __construct()
    {
        $this->kubernetesObject = new Kubernetes();
        $this->envSetObject = new EnvSet();
    }

     /**
     * Replace database URLs to match the target environment.
     *
     * @param string $target The target environment name.
     * @return bool True if the URL replacement is successful; otherwise, false.
     */
    public function replaceDatabaseURLs($target, $source, $blogID = null)
    {
        $containerExec = $this->kubernetesObject->getExecCommand($target);

        // Define the old and new URLs based on the environment names
        $sourceSiteURL = $this->envSetObject->getDomain($source);
        $targetSiteURL = $this->envSetObject->getDomain($target);

        if ($blogID !== null) {
            $extraOptions = "--all-tables-with-prefix 'wp_{$blogID}_*'";
        } else {
            $extraOptions = "--all-tables";
        }

        // Shell into container (kubernetes or docker) and run wp find and replace
        $command = "$containerExec wp search-replace";
        $command .= " $sourceSiteURL";
        $command .= " $targetSiteURL";
        $command .= " $extraOptions";
        $command .= " --network";
        $command .= " --precise";
        $command .= " --skip-columns=guid";
        $command .= " --report-changed-only";
        $command .= " --recurse-objects";

        passthru($command);
    }

    /**
     * Replaces one S3 bucket name with the target other s3 bucket name.
     *
     * @param string $sourceBucket        The source S3 bucket name.
     * @param string $targetBucket        The target CloudPlatform bucket name.
     * @param string $targetSiteURL       The URL of the target site.
     * @param string $containerExecCommand The command for executing within the container.
     * @return void
     */
    public function stringReplaceS3BucketName($targetBucket, $sourceBucket, $blogID = null)
    {
        if ($blogID !== null) {
            $extraOptions = "--all-tables-with-prefix 'wp_{$blogID}_*'";
        } else {
            $extraOptions = "--all-tables";
        }

        $containerExec = $this->kubernetesObject->getExecCommand($target);

        $command = "$containerExec wp search-replace";
        $command .= " $sourceBucket";
        $command .= " $targetBucket";
        $command .= " $extraOptions";
        $command .= " --network";
        $command .= " --precise";
        $command .= " --skip-columns=guid";
        $command .= " --report-changed-only";
        $command .= " --recurse-objects";

        echo "Run s3 bucket string replace: $sourceBucket with $targetBucket ";
        passthru($command);
    }
}