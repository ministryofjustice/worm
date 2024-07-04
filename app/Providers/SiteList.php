<?php

use Illuminate\Container\Container;

$container = Container::getInstance();

/**
 * Definitive site list that have production domains.
 *
 * This array contains a list of sites with their respective information.
 *
 * - 'blogID': The ID of the blog.
 * - 'domain': The domain of the site.
 * - 'path': The path of the site.
 */

$sites = [
    [
        "blogID" => 3,
        "domain" => "magistrates.judiciary.uk",
        "path" => "magistrates",
    ],

    [
        "blogID" => 5,
        "domain" => "ccrc.gov.uk",
        "path" => "ccrc",
    ],

    [
        "blogID" => 6,
        "domain" => "victimscommissioner.org.uk",
        "path" => "vc",
    ],

    [
        "blogID" => 7,
        "domain" => "layobservers.org",
        "path" => "lo",
    ],

    [
        "blogID" => 11,
        "domain" => "magistrates.judiciary.uk/cymraeg",
        "path" => "cymraeg",
    ],

    [
        "blogID" => 12,
        "domain" => "publicdefenderservice.org.uk",
        "path" => "pds",
    ],

    [
        "blogID" => 13,
        "domain" => "imb.org.uk",
        "path" => "imb",
    ],

    [
        "blogID" => 15,
        "domain" => "victimandwitnessinformation.org.uk",
        "path" => "vw",
    ],

    [
        "blogID" => 16,
        "domain" => "icrir.independent-inquiry.uk",
        "path" => "icrir",
    ],

    [
        "blogID" => 18,
        "domain" => "prisonandprobationjobs.gov.uk",
        "path" => "ppj",
    ],

    [
        "blogID" => 19,
        "domain" => "hmiprisons.justiceinspectorates.gov.uk",
        "path" => "hmip",
    ],

    [
        "blogID" => 23,
        "domain" => "nationalpreventivemechanism.org.uk",
        "path" => "npm",
    ],

    [
        "blogID" => 25,
        "domain" => "sifocc.org",
        "path" => "sifocc",
    ],

    [
        "blogID" => 29,
        "domain" => "brookhouseinquiry.org.uk",
        "path" => "brookhouse",
    ],

    [
        "blogID" => 30,
        "domain" => "lawcom.gov.uk",
        "path" => "lc",
    ],

    [
        "blogID" => 31,
        "domain" => "jobs.justice.gov.uk",
        "path" => "jj",
    ],

    [
        "blogID" => 33,
        "domain" => "cym.victimandwitnessinformation.org.uk",
        "path" => "vwcy",
    ],

    [
        "blogID" => 34,
        "domain" => "ppo.gov.uk",
        "path" => "ppo",
    ],
    [
        "blogID" => 37,
        "domain" => "my.imb.org.uk",
        "path" => "imbmembers-leg",
    ],

    [
        "blogID" => 39,
        "domain" => "members.layobservers.org",
        "path" => "layobservers-members",
    ],

    [
        "blogID" => 40,
        "domain" => "newfuturesnetwork.gov.uk",
        "path" => "nfn",
    ],

    [
        "blogID" => 41,
        "domain" => "andrewmalkinson.independent-inquiry.uk",
        "path" => "omagh",
    ],

    [
        "blogID" => 42,
        "domain" => "omagh.independent-inquiry.uk",
        "path" => "omagh",
    ],

    [
        "blogID" => 49,
        "domain" => "showcase.websitebuilder.service.justice.gov.uk",
        "path" => "showcase",
    ]

];


$container->instance('sites', $sites);
