<?php

namespace App\Helpers;

use LaravelZero\Framework\Commands\Command;
use App\Helpers\Kubernetes;
use Illuminate\Container\Container;

class EnvUtils
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
    public function checkSQLfileIsValid($filePath)
    {
        if (!$this->isSqlFile($filePath)) {
            throw new \InvalidArgumentException("File is not an SQL file type. ");
        }

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found.');
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

    /**
     * Get the domain for a specified environment and optionally a blog ID.
     *
     * This function retrieves the domain based on the environment provided. If the environment is 'prod' and a blog ID is provided,
     * it checks for the corresponding domain in a list of production domains. If the provided blog ID matches any blog ID in the
     * list, it returns the corresponding domain. Otherwise, it returns a default domain constructed using the site path.
     * If the environment is not 'prod', it constructs and returns a domain based on the environment.
     *
     * @param string $env The environment for which the domain is requested. Accepts 'prod' or any other environment string.
     * @param int|null $blogID The ID of the blog (optional). Required only if $env is 'prod'.
     * @return string The domain corresponding to the provided environment and blog ID (if applicable).
     */
    public function getDomain($env, $blogID = null)
    {

        if ($env === 'local') {
            return "hale.docker";
        }

        // SSOT hardcoded list of production domains
        // List can be updated in the SiteList.php
        $container = Container::getInstance();
        $sites = $container->get('sites');

        $env = strtolower($env);

        if ($env == 'prod' && !is_null($blogID)) {
            foreach ($sites as $site) {
                $domain = $site['domain'];
                $sitePath = $site['path'];
                $siteID = $site['blogID'];

                if ($blogID == $siteID) {
                    return $domain;
                } else {
                    return "websitebuilder.service.justice.gov.uk/$sitePath";
                }
            }
        }

        if ($env == 'prod' && is_null($blogID)) {
            return "websitebuilder.service.justice.gov.uk";
        }

        return "$env.websitebuilder.service.justice.gov.uk";
    }


    public function extractFileNameEnvironment($fileName)
    {
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

    /**
     * Check if the given filename indicates a multsite database file.
     *
     * This function uses a regular expression pattern to determine if the word "site"
     * is present in the given filename. If the word "site" is found in the filename,
     * it is considered to indicate a multisite database file.
     *
     * @param string $filename The filename to be checked.
     *
     * @return bool True if the filename indicates a multisite database file, false otherwise.
     */
    public function isMultisiteDbExportByFileName($filename)
    {
        // Define the regex pattern to match the word "site"
        $pattern = '/\bsite\b/';

        // Check if the pattern exists in the filename
        if (!preg_match($pattern, $filename)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Search for an array of keywords in a SQL file and require at least two words to be found.
     *
     * @param string $filePath The path to the SQL file.
     * @param array $searchWords An array of words to search for.
     * @return bool True if at least two words are found, false otherwise.
     */
    public function searchWordsInSqlFile($filePath, $searchWords)
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            return false;
        }

        // Open the file for reading
        if (!$handle = fopen($filePath, 'r')) {
            return false;
        }

        // Initialize counter for found words
        $foundCount = 0;

        // Read the file line by line
        while (($line = fgets($handle)) !== false) {
            // Check if any of the search words are found in the current line
            foreach ($searchWords as $searchWord) {
                if (strpos($line, $searchWord) !== false) {
                    $foundCount++;
                    // Break the loop if at least two words are found
                    if ($foundCount >= 2) {
                        fclose($handle);
                        return true;
                    }
                }
            }
        }

        // Close the file handle
        fclose($handle);

        // Return false if less than two words are found
        return false;
    }

    /**
     * Validate the target environment specified in the command.
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function validateTargetEnvironment($target)
    {
        $allowedEnvironments = ['prod', 'staging', 'dev', 'demo', 'local'];
        if (!in_array($target, $allowedEnvironments)) {
            throw new \InvalidArgumentException(
                "Invalid target environment specified: $target. " .
                "Allowed values are " . implode(', ', $allowedEnvironments) . "."
            );
        }
    }

    /**
     * Validate the file path specified in the command.
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function validateFilePath($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException(
                "Invalid file path specified: $filePath. " .
                "The file does not exist or is not readable."
            );
        }
    }

    /**
     * Validate the import type (multisite or single site) based on the file content and options.
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function validateMultisiteImport($filePath, $blogID)
    {
        $fileName = basename($filePath);

        // Check if the file is a multisite export based on its name
        $isMultisiteFileName = $this->isMultisiteDbExportByFileName($fileName);

        // Check if the file contains multisite database tables
        $isMultisiteDbTables = $this->searchWordsInSqlFile($filePath, ['wp_blogs', 'wp_site']);

        // Check if the import is invalid for single-site export
        if ($isMultisiteFileName && $isMultisiteDbTables && !empty($blogID)) {
            throw new \InvalidArgumentException(
                'Your DB file contains tables indicating it is not a single site import. ' .
                'Omit the --blogID option if you want to import an entire multisite but this will overwrite all blogs ' .
                'with the imported data.'
            );
        }

        // Check if the import is invalid for single-site import without --blogID
        if ($isMultisiteFileName === false && $isMultisiteDbTables === false && empty($blogID)) {
            throw new \InvalidArgumentException(
                'The file you are importing appears to contain a single site export however ' .
                'you have not included the --blogID option. ' .
                'Add --blogID option with the ID of the site you wish to import into.'
            );
        }
    }

    /**
     * Identify the source environment or custom source domain of an SQL file.
     *
     * This method analyzes the filename of the given SQL file to determine its source environment.
     * If the filename includes an environment indicator (e.g., prod, staging, dev), it returns
     * that environment name. Otherwise, it errors as we only want to handle db files
     * exported from our other environments at the moment.
     *
     * @param string $filePath The path to the SQL file.
     *
     * @return string The source environment or custom source domain.
     *
     * @throws \InvalidArgumentException When the identified environment is not valid.
     */
    public function identifySource($filePath)
    {
        $envName = $this->extractFileNameEnvironment(basename($filePath));

        if (in_array($envName, ['prod', 'staging', 'dev', 'demo', 'local'])) {
            return $envName;
        } else {
            throw new \InvalidArgumentException(
                'The selected database must originate from one of our CloudPlatform environments.'
            );
        }
    }

    /**
     * Update and upgrade the cloud-platform-cli package using Homebrew.
     *
     * This method updates Homebrew to ensure it has the latest package information
     * and then upgrades the cloud-platform-cli package to the latest version available.
     * It checks the status of the cloud-platform-cli package on the system and performs
     * the update and upgrade operation using Homebrew. To perform some task in
     * CloudPlatform we require this tool.
     *
     * @throws \InvalidArgumentException If the update and upgrade operation fails.
     *                                   The method exits with an error message.
     * @return void
     */
    public function updateCloudPlatformCli()
    {
        echo "[*] Checking cloud-platform-cli status on the system" . PHP_EOL;

        // Execute the Homebrew command to update and upgrade the cloud-platform-cli package
        exec("brew update && brew upgrade cloud-platform-cli 2>/dev/null", $output, $resultCode);

        // Check the result code of the command
        if ($resultCode !== 0) {
            // If the update and upgrade operation fails, throw an exception
            throw new \InvalidArgumentException(
                "Failed to update and upgrade the cloud-platform-cli package. Exiting."
            );
        }
    }

    /**
     * Get the non-production domain based on the target environment.
     *
     * This method returns the appropriate non-production domain based on the specified target environment.
     * If the target is set to 'local', the domain 'hale.docker' is returned. For other non-production environments,
     * the domain follows the pattern "[TARGET].websitebuilder.service.justice.gov.uk".
     *
     * @return string The non-production domain for the given target environment.
     */
    public function getNonProdDomain($target): string
    {
        return $target === 'local' ?
            'hale.docker' :
            "$target.websitebuilder.service.justice.gov.uk";
    }
}
