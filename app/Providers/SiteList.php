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
        "blogID" => 14,
        "domain" => "ppo.gov.uk",
        "path" => "ppo",
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
        "blogID" => 17,
        "domain" => "bold.websitebuilder.service.justice.gov.uk",
        "path" => "bold",
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
        "blogID" => 20,
        "domain" => "intranet.hmiprisons.justiceinspectorates.gov.uk",
        "path" => "hmipintranet",
    ],

    [
        "blogID" => 21,
        "domain" => "iapdeathsincustody.independent.gov.uk",
        "path" => "iapdc",
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
        "blogID" => 26,
        "domain" => "members.layobservers.org",
        "path" => "layobsmembers",
    ],

    [
        "blogID" => 29,
        "domain" => "brookhouseinquiry.org.uk",
        "path" => "brookhouse",
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
        "domain" => "archive.ppo.gov.uk",
        "path" => "ppo-archive",
    ],

    [
        "blogID" => 35,
        "domain" => "intranet.icrir.independent-inquiry.uk",
        "path" => "icrir-intranet",
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
        "path" => "mi",
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
    ],

    [
        "blogID" => 53,
        "domain" => "hmppsinsights.service.justice.gov.uk",
        "path" => "hmppsinsights",
    ],

    [
        "blogID" => 54,
        "domain" => "lawcom.gov.uk",
        "path" => "lawcomm",
    ],

    [
        "blogID" => 55,
        "domain" => "cym.victimandwitnessinformation.org.uk",
        "path" => "vwicy",
    ],

    [
        "blogID" => 59,
        "domain" => "dashboard.websitebuilder.service.justice.gov.uk",
        "path" => "dashboard",
    ],

    [
        "blogID" => 62,
        "domain" => "justiceinspectorates.gov.uk",
        "path" => "inspectorates",
    ],

    [
        "blogID" => 64,
        "domain" => "nottingham.independent-inquiry.uk",
        "path" => "nottingham",
    ]
];


    $container->instance('sites', $sites);
