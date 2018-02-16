<?php

namespace LibLynx\Connect;

use Symfony\Component\Cache\Simple\ArrayCache;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/common.php';

//------------------------------------------------------------------------------------
// Establish API credentials (store these in environment variables for repeated use)
//------------------------------------------------------------------------------------
$clientId = getenv('LIBLYNX_CLIENT_ID');
$clientSecret = getenv('LIBLYNX_CLIENT_SECRET');

if (empty($clientId)) {
    heading('API Credentials');

    $clientId = ask('LibLynx API Client ID', '/^\d+_[a-z0-9]+/');
    $clientSecret = ask('LibLynx API Client Secret', '/[a-z0-9]+/');

    echo "\n";
    echo "To avoid entering client ID and secret, set these environment variables\n";
    echo "export LIBLYNX_CLIENT_ID=$clientId\n";
    echo "export LIBLYNX_CLIENT_SECRET=$clientSecret\n";
    echo "\n";
}

//------------------------------------------------------------------------------------
// Ask for an IP and target URL to identify
//------------------------------------------------------------------------------------
heading('Identification request');
$ip = ask('IP address', '/^\d+\.\d+\.\d+\.\d+$/', '1.2.3.4');
$url = ask('Target URL', '/^https?:\/\/.*$/', 'http://www.example.com');



//------------------------------------------------------------------------------------
// Now we can set up our LibLynx client and attempt an identification...
//------------------------------------------------------------------------------------
$liblynx = new Client();

//this built in diagnostic logger can output to console or HTML, you can use any PSR-3 compliant logger thoughs
$logger = new DiagnosticLogger;
$liblynx->setLogger($logger);

//the LibLynx client requires that you use a PSR-16 cache, for this we simply provide an in-memory array cache
$cache = new ArrayCache();
$liblynx->setCache($cache);
$liblynx->setCredentials($clientId, $clientSecret);

//build our our identification request
$identification = new Identification();
$identification->ip = $ip;
$identification->url = $url;
$identification->user_agent = 'LibLynx-Client-Example-1';

//and away we go!
$identification = $liblynx->authorize($identification);

heading('Identification response');
if ($identification) {
    if ($identification->isIdentified()) {
        //good to go
        echo "identified as account named {$identification->account->account_name}\n";
    } else {
        echo "requires WAYF at {$identification->_links->wayf->href}\n";
    }
} else {
    //liblynx failed
    echo "API request failed\n";
}

//show trace of everything that happened
heading('Diagnostic logs');
$logger->dumpConsole();
echo "\n";
