<?php

use Illuminate\Container\Container;

$container = Container::getInstance();

/**
 * Definitive site list that have production domains.
 * This site list is pulled from our Prod site list API.
 * 
 * This array contains a list of sites with their respective information.
 *
 * - 'blogID': The ID of the blog.
 * - 'domain': The domain of the site.
 * - 'path': The path of the site.
 */

$url = 'https://websitebuilder.service.justice.gov.uk/wp-json/hc-rest/v1/sites/domain';

$response = file_get_contents($url);

if ($response === false) {
    echo "error fetching data.";
} else {
    $data = json_decode($response, true);
}

$container->instance('sites', $data);

