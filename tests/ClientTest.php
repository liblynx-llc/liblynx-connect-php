<?php

namespace LibLynx\Connect;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LibLynx\Connect\HTTPClient\HTTPClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Simple\ArrayCache;

class ClientTest extends TestCase
{
    public function testCredentialsFromServerVars()
    {
        $_SERVER['LIBLYNX_CLIENT_ID'] = 'foo';
        $_SERVER['LIBLYNX_CLIENT_SECRET'] = 'bar';

        $liblynx = new Client();
        $credentials = $liblynx->getCredentials();
        $this->assertEquals('foo', $credentials[0]);
        $this->assertEquals('bar', $credentials[1]);

        unset($_SERVER['LIBLYNX_CLIENT_ID']);
        unset($_SERVER['LIBLYNX_CLIENT_SECRET']);
    }

    /**
     * @expectedException \LibLynx\Connect\Exception\LogicException
     */
    public function testFailsWithoutCache()
    {
        $liblynx = new Client();
        $liblynx->setCredentials('testid', 'testsecret');

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->authorize($req);
    }

    /**
     * @expectedException \LibLynx\Connect\Exception\LogicException
     */
    public function testFailsWithoutCredentials()
    {
        $liblynx = new Client();
        $liblynx->setCache(new ArrayCache());

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->authorize($req);
    }

    public function testBadIdentification()
    {
        $mockOAuth = new MockHandler([
            $this->oauthTokenResponse(),
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        $mockAPI = new MockHandler([
            $this->entrypointResponse(),
            $this->badIdentificationResponse(),
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory = new HTTPClientFactory($mockAPI, $mockOAuth);
        $liblynx = new Client($httpFactory);
        $liblynx->setAPIRoot('http://localhost');

        //we use an in-memory cache for testing
        $liblynx->setCache(new ArrayCache());

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4.5';

        $liblynx->setCredentials('testid', 'testsecret');

        $identification = $liblynx->authorize($req);
        $this->assertNull($identification);
    }

    public function testPositiveIdentification()
    {
        $mockOAuth = new MockHandler([
            $this->oauthTokenResponse(),
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        $mockAPI = new MockHandler([
            $this->entrypointResponse(),
            $this->identifiedIdentificationResponse(),
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory = new HTTPClientFactory($mockAPI, $mockOAuth);
        $liblynx = new Client($httpFactory);
        $liblynx->setAPIRoot('http://localhost');

        //we use an in-memory cache for testing
        $liblynx->setCache(new ArrayCache());


        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->setCredentials('testid', 'testsecret');

        $identification = $liblynx->authorize($req);
        $identified = $identification->isIdentified();
        $this->assertTrue($identified, 'we expected to be identified');
    }

    /**
     * @expectedException \LibLynx\Connect\Exception\APIException
     */
    public function testEntryPointFailure()
    {
        $mockOAuth = new MockHandler([
            $this->oauthTokenResponse(),
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        $mockAPI = new MockHandler([
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory = new HTTPClientFactory($mockAPI, $mockOAuth);
        $liblynx = new Client($httpFactory);
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache(new ArrayCache());

        $liblynx->getEntryPoint('@new_identification');
    }

    /**
     * @expectedException \LibLynx\Connect\Exception\LogicException
     */
    public function testUnknownEntryPointFailure()
    {
        $mockOAuth = new MockHandler([
            $this->oauthTokenResponse(),
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        $mockAPI = new MockHandler([
            $this->entrypointResponse(),
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory = new HTTPClientFactory($mockAPI, $mockOAuth);
        $liblynx = new Client($httpFactory);
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache(new ArrayCache());
        $liblynx->setLogger(new NullLogger());

        $liblynx->getEntryPoint('@bad_entry');
    }

    public function testEntryPointCaching()
    {
        //we're going to use this cache in two clients
        $cache = new ArrayCache();

        $mockOAuth = new MockHandler([
            $this->oauthTokenResponse(),
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        $mockAPI = new MockHandler([
            $this->entrypointResponse(),
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory = new HTTPClientFactory($mockAPI, $mockOAuth);
        $liblynx = new Client($httpFactory);
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache($cache);

        $this->assertEquals('http://localhost/api/identifications', $liblynx->getEntryPoint('@new_identification'));


        //ok - now we have a warmed up cache, let's make a new client. This client will not expect an oauth request
        //to be made, as that should be cached
        $mockOAuth2 = new MockHandler([
            new RequestException("Unexpected OAuth request made by unit test", new Request('GET', 'test'))
        ]);

        //and we don't have an entrypoint response here either
        $mockAPI2 = new MockHandler([
            new RequestException("Unexpected request made by unit test", new Request('GET', 'test'))
        ]);

        $httpFactory2 = new HTTPClientFactory($mockAPI2, $mockOAuth2);
        $liblynx2 = new Client($httpFactory2);
        $liblynx2->setAPIRoot('http://localhost');
        $liblynx2->setCredentials('testid', 'testsecret');
        $liblynx2->setCache($cache);

        //if this succeeds, then both the OAuth token and the entry point resource were successfully cached
        $this->assertEquals('http://localhost/api/identifications', $liblynx2->getEntryPoint('@new_identification'));
    }

    protected function oauthTokenResponse()
    {
        $oauthResponse = [
            "access_token" => "YzI0YTE4YjgyZTAzYTMzNWEyOWVhMWU0NjZkZDU3ZDFlODFhNWNmYWI2NjZmODJjYmExZTBlYjNkYzYyYmFhYQ",
            "expires_in" => 3600,
            "token_type" => "bearer",
            "scope" => null,
            "refresh_token" => "MDM3ZDlhMzY1YWQ1MGRiNjRiMjI4OTgwYWY3M2IzODM5NTM2NjIxNmE5YTQyMGI5ZmQ2OWJiOGM1MTA2N2YxNQ"
        ];
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($oauthResponse));
    }

    protected function entrypointResponse()
    {
        $entrypoints = <<<JSON
        {
            "_links": {
              "self": {
                "href": "http://localhost/api"
              },
              "@new_identification": {
                 "href": "http://localhost/api/identifications"
              },
              "@get_identification": {
                 "href": "http://localhost/api/identifications/{id}"
              }
            }
        }
JSON;
        return new Response(200, ['Content-Type' => 'application/json'], $entrypoints);
    }

    protected function identifiedIdentificationResponse()
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        return new Response(200, ['Content-Type' => 'application/json'], $json);
    }

    protected function badIdentificationResponse()
    {
        $json = <<<JSON
        {
          "status_code": 400,
          "message": "Validation Failed",
          "errors": {
            "url": "ERROR: This value should not be blank."
          }
        }
JSON;
        return new Response(400, ['Content-Type' => 'application/json'], $json);
    }
}
