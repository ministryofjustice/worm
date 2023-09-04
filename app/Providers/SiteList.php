<?php

use Illuminate\Container\Container;

$container = Container::getInstance();

/**
 * Definitive site list.
 *
 * This array contains a list of sites with their respective information.
 *
 * Each site is represented by a unique 3-character code as the key, and the value is an array
 * containing the following information:
 *
 * - 'blogID': The ID of the blog.
 * - 'domain': The domain of the site.
 * - 'path': The path of the site.
 */
$sites = [
    "mag" => [
        "blogID" => 3,
        "domain" => "magistrates.judiciary.uk",
        "path" => "magistrates",
    ],

    "ccr" => [
        "blogID" => 5,
        "domain" => "ccrc.gov.uk",
        "path" => "ccrc",
    ],

    "vic" => [
        "blogID" => 6,
        "domain" => "victimscommissioner.org.uk",
        "path" => "vc",
    ],

    "cym" => [
        "blogID" => 11,
        "domain" => "magistrates.judiciary.uk/cymraeg",
        "path" => "cymraeg",
    ],

    "pds" => [
        "blogID" => 12,
        "domain" => "publicdefenderservice.org.uk",
        "path" => "pds",
    ],

    "imb" => [
        "blogID" => 13,
        "domain" => "imb.org.uk",
        "path" => "imb",
    ],

    "icr" => [
        "blogID" => 16,
        "domain" => "icrir.independent-inquiry.uk",
        "path" => "icrir",
    ],

    "brh" => [
        "blogID" => 29,
        "domain" => "brookhouseinquiry.org.uk",
        "path" => "brookhouse",
    ],
    ];

    $container->instance('sites', $sites);
