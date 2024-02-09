<?php

namespace App\Helpers;

use App\Helpers\Kubernetes;

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
        $kubernetesObject = new Kubernetes($target);
        $containerExec = $kubernetesObject->getExecCommand($target);

        if (is_numeric($blogID)) {
            // Check if the site exists
            $siteCheck = rtrim(shell_exec("$containerExec wp site list --site__in=$blogID --field=blog_id --format=csv"));

            // Return true if the site exists, false otherwise
            return !empty($siteCheck);
        }

        return false;
    }
}
