<?php

namespace App\Helpers;

use LaravelZero\Framework\Commands\Command;

use App\Helpers\Kubernetes;
use Illuminate\Container\Container;

class EnvSet
{

    /**
     * Generate a unique SQL file name based on the environment and optional blog ID.
     *
     * @param string $env    The target environment.
     * @param int|null $blogID Optional blog ID to include in the file name.
     *
     * @return string The generated SQL file name with a timestamp.
     *
     * This function creates a unique SQL file name for database exports. It includes the specified
     * target environment and, if provided, appends the blog ID to the file name. The resulting file
     * name follows the format 'hale-platform-{env}-site-{blogID}-{timestamp}.sql'.
     */
    public function generateFileName($env, $blogID = null)
    {
        $addLabel = '';

        if (!empty($blogID)) {
            // Add the blog ID to the db file
            $addLabel = '-site-' . $blogID;
        }

        return 'hale-platform-' . $env . $addLabel . '-' . date("Y-m-d-H-i-s") . '.sql';
    }

    /**
     * Check if a WordPress site exists using WP-CLI in a Kubernetes container.
     *
     * @param string $target The target environment.
     * @param int $blogID    The blog ID to check for existence.
     *
     * @return bool True if the site exists, false otherwise.
     *
     * This function verifies the existence of a WordPress site within a Kubernetes container using WP-CLI.
     * It checks for the specified blog ID and returns true if the site is found; otherwise, it returns false.
     */
    public function checkSiteExists($target, $blogID)
    {
        $kubernetesObject = new Kubernetes();
        $containerExec = $kubernetesObject->getExecCommand($target);

        if (is_numeric($blogID)) {
            // Check if the site exists
            $siteCheck = rtrim(shell_exec("$containerExec wp site list --site__in=$blogID --field=blog_id --format=csv"));

            // Return true if the site exists, false otherwise
            return !empty($siteCheck);
        }

        return false;
    }

    /**
     * Check if the file exists locally and is of right format.
     *
     * This method checks whether the SQL file exists
     *
     * @param string $path The path of the file.
     * @return bool True if the file exists; otherwise, false.
     */
    public function checkSQLfileIsValid($file)
    {

        if (!$this->isSqlFile($file)) {
            echo 'File is not an SQL file type.' . PHP_EOL;
            exit(0);
        }

        $wordpressPathText =
            'File not found.' . PHP_EOL;

        if (!file_exists($file)) {
            echo $wordpressPathText;
            exit(0);
        }

        return true;
    }

    /**
     * Check if a file is an SQL file.
     *
     * This function checks if the given file has a ".sql" extension, indicating that it is likely
     * an SQL file.
     *
     * @param string $filePath The path to the file.
     *
     * @return bool True if the file has a ".sql" extension; otherwise, false.
     */
    private function isSqlFile($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension) === 'sql';
    }

    public function getDomain($target, $blogID = null) 
    {
        // SSOT hardcoded list of production domains
        // List can be updated in the SiteList.php
        $container = Container::getInstance();
        $sites = $container->get('sites');

        $target = strtolower($target);

        if ($target == 'prod') {
            foreach ($sites as $site) {
                $domain = $site['domain'];
                $sitePath = $site['path'];
                $siteID = $site['blogID'];
    
                if ($blogID == $siteID) {
                    return $domain;
                } else {
                    return "hale-platform-prod.apps.live.cloud-platform.service.justice.gov.uk/$sitePath";
                }
            }
        }


        return "hale-platform-$target.apps.live.cloud-platform.service.justice.gov.uk";


        // $kubernetesObject = new Kubernetes();
        // $slug = $kubernetesObject->getSiteSlugByBlogId($target, $blogID);

        //return "hale-platform-$target.apps.live.cloud-platform.service.justice.gov.uk/$slug";

    }

    public function extractFileNameEnvironment($fileName) {

        $possibleEnvironments = ['dev', 'prod', 'staging', 'demo', 'local'];

        // Generate the regex pattern dynamically based on possible environments
        $pattern = '/hale-platform-(' . implode('|', $possibleEnvironments) . ')/';

        // Perform regex match
        if (preg_match($pattern, $fileName, $matches)) {
            // $matches[1] will contain the environment word
            return $matches[1];
        }

        // Return null if no match
        return null;
    }
}