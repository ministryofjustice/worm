<?php

use Illuminate\Container\Container;

$container = Container::getInstance();

/**
 * Definitive site list that have production domains.
 * This site list is pulled from our Prod site list API.
 *
 * The API contains a list of sites with their respective information.
 *
 * - 'blogID': The ID of the blog.
 * - 'domain': The domain of the site.
 * - 'slug': The unique given directory path of the site.
 */

// Initialize a cURL session
$ch = curl_init();

// Set URL we want
curl_setopt($ch, CURLOPT_URL, 'https://websitebuilder.service.justice.gov.uk/wp-json/hc-rest/v1/sites/domain');

// Output as a string not to browser
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set a timeout
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Follow redirects (if ever useful)
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Make sure to verify SSL certs
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Optional but useful additions
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);     // Timeout for connection phase only
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);           // Limit number of redirects

// Execute response
$response = curl_exec($ch);

// Check for cURL errors
// curl_errno() returns the error number (0 = no error)
if (curl_errno($ch)) {
    echo "cURL Error Number: " . curl_errno($ch) . "\n";
    echo "cURL Error Message: " . curl_error($ch) . "\n";
    $data = null;
} else {
    // Get HTTP response code
    // Server responded with 200 OK, 404 Not Found, etc.
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Useful debug info about the request
    #$info = curl_getinfo($ch);
    #echo "HTTP Code: " . $httpCode . "\n";
    #echo "Content Type: " . $info['content_type'] . "\n";
    #echo "Total Time: " . $info['total_time'] . " seconds\n";
    #echo "Download Size: " . $info['size_download'] . " bytes\n";

    if ($httpCode === 200) {
        // Process successful response
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON Decode Error: " . json_last_error_msg() . "\n";
            $data = null;
        }
    } else {
        echo "HTTP Error: Received status code $httpCode\n";
        $data = null;
    }
}

// Always close the cURL handle to free up resources
curl_close($ch);

if ($data !== null) {
    $container->instance('sites', $data);
}
