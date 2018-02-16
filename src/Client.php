<?php

namespace LibLynx\Connect;

use GuzzleHttp\Exception\RequestException;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client as GuzzleClient;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr16CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use LibLynx\Connect\SimpleCacheTokenPersistence;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * LibLynx Connect API client
 *
 * $liblynx=new Liblynx\Connect\Client;
 * $liblynx->setCredentials('your client id', 'your client secret');
 *
 * //must set a PSR-16 cache - this can be used for testing
 * $liblynx->setCache(new \Symfony\Component\Cache\Simple\ArrayCache);
 *
 * $identification=$liblynx->authorize(Identification::fromSuperglobals());
 * if ($identification->isIdentified()) {
 *     //good to go
 * }
 *
 * if ($identification->requiresWayf()) {
 *     $identification->doWayfRedirect();
 * }
 *
 * @package LibLynx\Connect
 */
class Client implements LoggerAwareInterface
{
    private $apiroot = 'https://connect.liblynx.com';

    /** @var string client ID obtain from LibLynx Connect admin portal */
    private $clientId;

    /** @var string client secret obtain from LibLynx Connect admin portal */
    private $clientSecret;

    /** @var GuzzleClient HTTP client for API requests */
    private $guzzle;

    /** @var array entry point resource */
    private $entrypoint;

    /** @var callable RequestStack handler for API requests */
    private $apiHandler = null;

    /** @var callable RequestStack handler for OAuth2 requests */
    private $oauth2Handler = null;

    /** @var CacheInterface */
    protected $cache;

    /** @var LoggerInterface */
    protected $log;

    /**
     * Create new LibLynx API client
     */
    public function __construct()
    {
        if (isset($_SERVER['LIBLYNX_CLIENT_ID'])) {
            $this->clientId = $_SERVER['LIBLYNX_CLIENT_ID'];
        }
        if (isset($_SERVER['LIBLYNX_CLIENT_SECRET'])) {
            $this->clientSecret = $_SERVER['LIBLYNX_CLIENT_SECRET'];
        }

        $this->log = new NullLogger();
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;
    }

    /**
     * Set API root
     * This will be provided to you by LibLynx Technical Support
     * @param string $url
     */
    public function setAPIRoot($url)
    {
        $this->apiroot = $url;
    }

    /**
     * Set OAuth2.0 client credentials
     * These will be provided to you by LibLynx Technical Support
     *
     * @param string $clientId
     * @param string $clientSecret
     */
    public function setCredentials($clientId, $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return array containing client id and secret
     */
    public function getCredentials()
    {
        return [$this->clientId, $this->clientSecret];
    }

    /**
     * Attempt an identification using the LibLynx API
     *
     * @param Identification $request
     * @return Identification|null
     */
    public function authorize(Identification $request)
    {
        $identification = null;

        $payload = $request->getRequestJSON();
        $response = $this->post('@new_identification', $payload);
        if (isset($response->id)) {
            $identification = Identification::fromJSON($response);
            $this->log->info(
                'Identification request for ip {ip} on URL {url} succeeded status={status} id={id}',
                [
                    'status' => $identification->status,
                    'id' => $identification->id,
                    'ip' => $identification->ip,
                    'url' => $identification->url
                ]
            );
        } else {
            //failed
            $this->log->critical('Identification request failed {payload}', ['payload' => $payload]);
            return null;
        }

        return $identification;
    }

    /**
     * This is primarily to facilitate testing - we can add a MockHandler to return
     * test responses
     *
     * @param callable $handler
     * @return self
     */
    public function setAPIHandler(callable $handler)
    {
        $this->apiHandler = $handler;
        return $this;
    }

    /**
     * This is primarily to facilitate testing - we can add a MockHandler to return
     * test responses
     *
     * @param callable $handler
     * @return self
     */
    public function setOAuth2Handler(callable $handler)
    {
        $this->oauth2Handler = $handler;
        return $this;
    }

    public function get($entrypoint)
    {
        return $this->makeAPIRequest('GET', $entrypoint);
    }

    public function post($entrypoint, $json)
    {
        return $this->makeAPIRequest('POST', $entrypoint, $json);
    }

    public function put($entrypoint, $json)
    {
        return $this->makeAPIRequest('PUT', $entrypoint, $json);
    }

    protected function makeAPIRequest($method, $entrypoint, $json = null)
    {
        $this->log->debug('{method} {entrypoint} {json}', [
                'method' => $method,
                'entrypoint' => $entrypoint,
                'json' => $json
            ]);
        $url = $this->resolveEntryPoint($entrypoint);
        $client = $this->getClient();

        $this->log->debug('{entrypoint} = {url}', ['entrypoint' => $entrypoint, 'url' => $url]);

        $headers = ['Accept' => 'application/json'];
        if (!empty($body)) {
            $headers['Content-Type'] = 'application/json';
        }

        $request = new \GuzzleHttp\Psr7\Request($method, $url, $headers, $json);

        try {
            $response = $client->send($request);

            $this->log->debug('{method} {entrypoint} succeeded {status}', [
                'method' => $method,
                'entrypoint' => $entrypoint,
                'status' => $response->getStatusCode(),
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $this->log->error(
                '{method} {entrypoint} {json} failed ({status}): {body}',
                [
                    'method' => $method,
                    'json' => $json,
                    'entrypoint' => $entrypoint,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()
                ]
            );
        }

        $payload = json_decode($response->getBody());
        return $payload;
    }

    public function resolveEntryPoint($nameOrUrl)
    {
        if ($nameOrUrl[0] === '@') {
            return $this->getEntryPoint($nameOrUrl);
        }
        //it's a URL
        return $nameOrUrl;
    }

    public function getEntryPoint($name)
    {
        if (!is_array($this->entrypoint)) {
            $cache = $this->getCache();
            $key = 'entrypoint' . $this->clientId;
            if ($cache->has($key)) {
                $this->log->debug('loading entrypoint from persistent cache');
                $this->entrypoint = $cache->get($key);
            } else {
                $this->log->debug('entrypoint not cached, requesting from API');
                $client = $this->getClient();

                $request = new \GuzzleHttp\Psr7\Request('GET', 'api', [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

                try {
                    $response = $client->send($request);

                    $payload = json_decode($response->getBody());
                    if (is_object($payload) && isset($payload->_links)) {
                        $this->log->info('entrypoint loaded from API and cached');
                        $this->entrypoint = $payload;
                        $cache->set($key, $payload, 86400);
                    }
                } catch (RequestException $e) {
                    throw $e;
                }
            }
        } else {
            $this->log->debug('using previously loaded entrypoint');
        }

        if (!isset($this->entrypoint->_links->$name->href)) {
            throw new \InvalidArgumentException("Invalid LibLynx API entrypoint $name requested");
        }

        return $this->entrypoint->_links->$name->href;
    }

    public function getCache()
    {
        if (is_null($this->cache)) {
            throw new \RuntimeException('LibLynx Connect Client requires a PSR-16 compatible cache');
        }
        return $this->cache;
    }

    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Internal helper to provide an OAuth2 capable HTTP client
     */
    protected function getClient()
    {
        if (empty($this->clientId)) {
            throw new \BadMethodCallException('Cannot make API calls until setCredentials has been called');
        }
        if (!is_object($this->guzzle)) {
            //create our handler stack (which may be mocked in tests) and add the oauth and cache middleware
            $handlerStack = HandlerStack::create($this->apiHandler);
            $handlerStack->push($this->createOAuth2Middleware());
            $handlerStack->push($this->createCacheMiddleware(), 'cache');

            //now we can make our client
            $this->guzzle = new GuzzleClient([
                'handler' => $handlerStack,
                'auth' => 'oauth',
                'base_uri' => $this->apiroot
            ]);
        }

        return $this->guzzle;
    }

    protected function createOAuth2Middleware(): OAuth2Middleware
    {
        $handlerStack = HandlerStack::create($this->oauth2Handler);

        // Authorization client - this is used to request OAuth access tokens
        $reauth_client = new GuzzleClient([
            'handler' => $handlerStack,
            // URL for access_token request
            'base_uri' => $this->apiroot . '/oauth/v2/token',
        ]);
        $reauth_config = [
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret
        ];
        $grant_type = new ClientCredentials($reauth_client, $reauth_config);
        $oauth = new OAuth2Middleware($grant_type);

        //use our cache to store tokens
        $oauth->setTokenPersistence(new SimpleCacheTokenPersistence($this->getCache()));

        return $oauth;
    }

    protected function createCacheMiddleware(): CacheMiddleware
    {
        return new CacheMiddleware(
            new PrivateCacheStrategy(
                new Psr16CacheStorage($this->cache)
            )
        );
    }
}
