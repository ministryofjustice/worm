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

    "lob" => [
        "blogID" => 7,
        "domain" => "layobservers.org",
        "path" => "lo",
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

    "vaw" => [
        "blogID" => 15,
        "domain" => "victimandwitnessinformation.org.uk",
        "path" => "vw",
    ],

    "icr" => [
        "blogID" => 16,
        "domain" => "icrir.independent-inquiry.uk",
        "path" => "icrir",
    ],

    "ppj" => [
        "blogID" => 18,
        "domain" => "prisonandprobationjobs.gov.uk",
        "path" => "ppj",
    ],

    "npm" => [
        "blogID" => 23,
        "domain" => "nationalpreventivemechanism.org.uk",
        "path" => "npm",
    ],

    "brh" => [
        "blogID" => 29,
        "domain" => "brookhouseinquiry.org.uk",
        "path" => "brookhouse",
    ],

    "lwc" => [
        "blogID" => 30,
        "domain" => "lawcom.gov.uk",
        "path" => "lc",
    ],

    "jjb" => [
        "blogID" => 31,
        "domain" => "jobs.justice.gov.uk",
        "path" => "jj",
    ],

    "ppo" => [
        "blogID" => 34,
        "domain" => "ppo.gov.uk",
        "path" => "ppo",
    ],

    "sif" => [
        "blogID" => 36,
        "domain" => "sifocc.org",
        "path" => "sifocc",
    ],

    "mib" => [
        "blogID" => 37,
        "domain" => "my.imb.org.uk",
        "path" => "imbmembers-leg",
    ],

    "mlo" => [
        "blogID" => 39,
        "domain" => "members.layobservers.org",
        "path" => "layobservers-members",
    ],
    ];

    $container->instance('sites', $sites);
