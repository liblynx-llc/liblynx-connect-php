<?php

namespace LibLynx\Connect;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
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
     * @expectedException \RuntimeException
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
     * @expectedException \BadMethodCallException
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

        $liblynx = new Client();
        $liblynx->setAPIRoot('http://localhost');

        //we use an in-memory cache for testing
        $liblynx->setCache(new ArrayCache());

        $liblynx->setOAuth2Handler($mockOAuth);
        $liblynx->setApiHandler($mockAPI);

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

        $liblynx = new Client();
        $liblynx->setAPIRoot('http://localhost');

        //we use an in-memory cache for testing
        $liblynx->setCache(new ArrayCache());

        $liblynx->setOAuth2Handler($mockOAuth);
        $liblynx->setApiHandler($mockAPI);

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
     * @expectedException \GuzzleHttp\Exception\RequestException
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

        $liblynx = new Client();
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache(new ArrayCache());

        $liblynx->setOAuth2Handler($mockOAuth);
        $liblynx->setApiHandler($mockAPI);

        $liblynx->getEntryPoint('@new_identification');
    }

    /**
     * @expectedException \InvalidArgumentException
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

        $liblynx = new Client();
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache(new ArrayCache());

        $liblynx->setOAuth2Handler($mockOAuth);
        $liblynx->setApiHandler($mockAPI);

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

        $liblynx = new Client();
        $liblynx->setAPIRoot('http://localhost');
        $liblynx->setCredentials('testid', 'testsecret');
        $liblynx->setCache($cache);

        $liblynx->setOAuth2Handler($mockOAuth);
        $liblynx->setApiHandler($mockAPI);

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

        $liblynx2 = new Client();
        $liblynx2->setAPIRoot('http://localhost');
        $liblynx2->setCredentials('testid', 'testsecret');
        $liblynx2->setCache($cache);

        $liblynx2->setOAuth2Handler($mockOAuth2);
        $liblynx2->setApiHandler($mockAPI2);

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
        $json = <<<JSON
        {
          "id": "1fd03a8e5d4dbad4146a38ba15db82c7",
          "status": "identified",
          "ip": "1.2.3.66",
          "url": "http://example.com",
          "created": "2014-11-13T19:50:45+0000",
          "account": {
            "id": 1,
            "account_name": "Referrer Testing Account",
            "publisher": {
              "id": 1,
              "publisher_name": "LibLynx"
            },
            "type": "publisher"
          },
          "publisher": {
            "id": 1,
            "publisher_name": "LibLynx"
          },
          "_links": {
            "self": {
              "href": "http://172.17.8.101/api/identifications/1fd03a8e5d4dbad4146a38ba15db82c7"
            }
          }
        }
JSON;
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
