<?php

namespace LibLynx\Connect\HTTPClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client as GuzzleClient;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr16CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\SimpleCache\CacheInterface;

/**
 * Provides the means for creating an OAuth2-capable, cache-aware HTTP client
 *
 * This can also be modified to provide the means to test the client
 *
 * @package LibLynx\Connect
 */
class HTTPClientFactory
{
    /** @var CacheInterface */
    protected $cache;

    /** @var callable RequestStack handler for API requests */
    private $apiHandler = null;

    /** @var callable RequestStack handler for OAuth2 requests */
    private $oauth2Handler = null;

    public function __construct(callable $apiHandler = null, callable $oauth2Handler = null)
    {
        $this->apiHandler = $apiHandler;
        $this->oauth2Handler = $oauth2Handler;
    }

    public function create($apiRoot, $clientId, $clientSecret, CacheInterface $cache) : ClientInterface
    {
        //create our handler stack (which may be mocked in tests) and add the oauth and cache middleware
        $handlerStack = HandlerStack::create($this->apiHandler);
        $handlerStack->push($this->createOAuth2Middleware($apiRoot, $clientId, $clientSecret, $cache));
        $handlerStack->push($this->createCacheMiddleware($cache), 'cache');

        //now we can make our client
        $client = new GuzzleClient([
            'handler' => $handlerStack,
            'auth' => 'oauth',
            'base_uri' => $apiRoot
        ]);

        return $client;
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

    protected function createOAuth2Middleware($apiRoot, $id, $secret, CacheInterface $cache): OAuth2Middleware
    {
        $handlerStack = HandlerStack::create($this->oauth2Handler);

        // Authorization client - this is used to request OAuth access tokens
        $reauth_client = new GuzzleClient([
            'handler' => $handlerStack,
            // URL for access_token request
            'base_uri' => $apiRoot . '/oauth/v2/token',
        ]);
        $reauth_config = [
            "client_id" => $id,
            "client_secret" => $secret
        ];
        $grant_type = new ClientCredentials($reauth_client, $reauth_config);
        $oauth = new OAuth2Middleware($grant_type);

        //use our cache to store tokens
        $oauth->setTokenPersistence(new SimpleCacheTokenPersistence($cache));

        return $oauth;
    }

    protected function createCacheMiddleware(CacheInterface $cache): CacheMiddleware
    {
        return new CacheMiddleware(
            new PrivateCacheStrategy(
                new Psr16CacheStorage($cache)
            )
        );
    }
}
