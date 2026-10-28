<?php

namespace LibLynx\Connect;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use LibLynx\Connect\HTTPClient\HTTPClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ClientTest extends TestCase
{
    public function testCredentialsFromServerVars(): void
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

    public function testFailsWithoutCache(): void
    {
        $this->expectException(\LibLynx\Connect\Exception\LogicException::class);
        $liblynx = new Client();
        $liblynx->setCredentials('testid', 'testsecret');

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->authorize($req);
    }

    private function createArrayCache(): CacheInterface
    {
        $arrayAdapter = new ArrayAdapter();
        return new Psr16Cache($arrayAdapter);
    }

    public function testFailsWithoutCredentials(): void
    {
        $this->expectException(\LibLynx\Connect\Exception\LogicException::class);
        $liblynx = new Client();

        $liblynx->setCache($this->createArrayCache());

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->authorize($req);
    }

    public function testBadIdentification(): void
    {
        $this->expectException(\LibLynx\Connect\Exception\APIException::class);
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
        $liblynx->setCache($this->createArrayCache());

        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4.5';

        $liblynx->setCredentials('testid', 'testsecret');

        $identification = $liblynx->authorize($req);
    }

    public function testPositiveIdentification(): void
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
        $liblynx->setCache($this->createArrayCache());


        $req = new IdentificationRequest();
        $req->ip = '1.2.3.4';
        $req->referrer = 'http://example.com';
        $req->url = 'http://example.com';

        $liblynx->setCredentials('testid', 'testsecret');

        $identification = $liblynx->authorize($req);
        $identified = $identification->isIdentified();
        $this->assertTrue($identified, 'we expected to be identified');
    }

    public function testEntryPointFailure(): void
    {
        $this->expectException(\LibLynx\Connect\Exception\APIException::class);
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
        $liblynx->setCache($this->createArrayCache());

        $liblynx->getEntryPoint('@new_identification');
    }

    public function testUnknownEntryPointFailure(): void
    {
        $this->expectException(\LibLynx\Connect\Exception\LogicException::class);
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
        $liblynx->setCache($this->createArrayCache());
        $liblynx->setLogger(new NullLogger());

        $liblynx->getEntryPoint('@bad_entry');
    }

    public function testEntryPointCaching(): void
    {
        //we're going to use this cache in two clients
        $cache = $this->createArrayCache();

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

    protected function oauthTokenResponse(): Response
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

    protected function entrypointResponse(): Response
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

    protected function identifiedIdentificationResponse(): Response
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        return new Response(200, ['Content-Type' => 'application/json'], $json);
    }

    protected function badIdentificationResponse(): Response
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
