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
        "blogID" => 1,
        "domain" => "websitebuilder.service.justice.gov.uk",
        "path" => "",
    ],

    [
        "blogID" => 2,
        "domain" => "websitebuilder.service.justice.gov.uk/playground",
        "path" => "playground",
    ],

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
        "blogID" => 8,
        "domain" => "websitebuilder.service.justice.gov.uk/hale-help",
        "path" => "hale-help",
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
        "blogID" => 22,
        "domain" => "websitebuilder.service.justice.gov.uk/imbmembers",
        "path" => "imbmembers",
    ],

    [
        "blogID" => 23,
        "domain" => "nationalpreventivemechanism.org.uk",
        "path" => "npm",
    ],

    [
        "blogID" => 24,
        "domain" => "websitebuilder.service.justice.gov.uk/hmcpsi",
        "path" => "hmcpsi",
    ],

    [
        "blogID" => 25,
        "domain" => "sifocc.org",
        "path" => "sifocc",
    ],

    [
        "blogID" => 26,
        "domain" => "https://members.layobservers.org/",
        "path" => "layobsmembers",
    ],

    [
        "blogID" => 27,
        "domain" => "websitebuilder.service.justice.gov.uk/core",
        "path" => "core",
    ],

    [
        "blogID" => 28,
        "domain" => "websitebuilder.service.justice.gov.uk/hale-design-history",
        "path" => "hale-design-history",
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
        "blogID" => 32,
        "domain" => "websitebuilder.service.justice.gov.uk/hmi-probation",
        "path" => "hmi-probation",
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
        "blogID" => 43,
        "domain" => "websitebuilder.service.justice.gov.uk/cjji",
        "path" => "cjji",
    ],

    [
        "blogID" => 45,
        "domain" => "websitebuilder.service.justice.gov.uk/ccrc-launchpad",
        "path" => "ccrc-launchpad",
    ],

    [
        "blogID" => 46,
        "domain" => "websitebuilder.service.justice.gov.uk/laajobs",
        "path" => "laajobs",
    ],

    [
        "blogID" => 47,
        "domain" => "websitebuilder.service.justice.gov.uk/laalearning",
        "path" => "laalearning",
    ],

    [
        "blogID" => 48,
        "domain" => "websitebuilder.service.justice.gov.uk/website-builder",
        "path" => "website-builder",
    ],

    [
        "blogID" => 49,
        "domain" => "showcase.websitebuilder.service.justice.gov.uk",
        "path" => "showcase",
    ],

    [
        "blogID" => 50,
        "domain" => "websitebuilder.service.justice.gov.uk/vwi",
        "path" => "vwi",
    ],

    [
        "blogID" => 51,
        "domain" => "websitebuilder.service.justice.gov.uk/nipubhistory",
        "path" => "nipubhistory",
    ],

    [
        "blogID" => 52,
        "domain" => "websitebuilder.service.justice.gov.uk/pecs",
        "path" => "pecs",
    ],

    [
        "blogID" => 53,
        "domain" => "hmppsinsights.service.justice.gov.uk",
        "path" => "hmppsinsights",
    ],

    [
        "blogID" => 54,
        "domain" => "websitebuilder.service.justice.gov.uk/lawcomm",
        "path" => "lawcomm",
    ],

    [
        "blogID" => 55,
        "domain" => "cym.victimandwitnessinformation.org.uk",
        "path" => "vwicy",
    ],

    [
        "blogID" => 56,
        "domain" => "websitebuilder.service.justice.gov.uk/cjsm",
        "path" => "cjsm",
    ],

    [
        "blogID" => 57,
        "domain" => "websitebuilder.service.justice.gov.uk/ipa",
        "path" => "ipa",
    ],

    [
        "blogID" => 58,
        "domain" => "websitebuilder.service.justice.gov.uk/research-community",
        "path" => "research-community",
    ],

    [
        "blogID" => 59,
        "domain" => "dashboard.websitebuilder.service.justice.gov.uk",
        "path" => "dashboard",
    ]
];


    $container->instance('sites', $sites);
