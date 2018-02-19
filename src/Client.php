<?php

namespace LibLynx\Connect;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use LibLynx\Connect\Exception\APIException;
use LibLynx\Connect\Exception\LogicException;
use LibLynx\Connect\HTTPClient\HTTPClientFactory;
use LibLynx\Connect\Resource\Identification;
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

    /** @var ClientInterface HTTP client for API requests */
    private $guzzle;

    /** @var \stdClass entry point resource */
    private $entrypoint;

    /** @var CacheInterface */
    protected $cache;

    /** @var LoggerInterface */
    protected $log;

    /** @var HTTPClientFactory */
    protected $httpClientFactory;

    /**
     * Create new LibLynx API client
     */
    public function __construct(HTTPClientFactory $clientFactory = null)
    {
        if (isset($_SERVER['LIBLYNX_CLIENT_ID'])) {
            $this->clientId = $_SERVER['LIBLYNX_CLIENT_ID'];
        }
        if (isset($_SERVER['LIBLYNX_CLIENT_SECRET'])) {
            $this->clientSecret = $_SERVER['LIBLYNX_CLIENT_SECRET'];
        }

        $this->log = new NullLogger();
        $this->httpClientFactory = $clientFactory ?? new HTTPClientFactory;
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
     * An alternative root may be provided to you by LibLynx Technical Support
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
     * @param IdentificationRequest $request
     * @return Identification|null
     */
    public function authorize(IdentificationRequest $request)
    {
        $payload = $request->getRequestJSON();
        $response = $this->post('@new_identification', $payload);
        if (!isset($response->id)) {
            //failed
            $this->log->critical('Identification request failed {payload}', ['payload' => $payload]);
            return null;
        }

        $identification = new Identification($response);
        $this->log->info(
            'Identification request for ip {ip} on URL {url} succeeded status={status} id={id}',
            [
                'status' => $identification->status,
                'id' => $identification->id,
                'ip' => $identification->ip,
                'url' => $identification->url
            ]
        );

        return $identification;
    }

    /**
     * General purpose 'GET' request against API
     * @param $entrypoint string contains either an @entrypoint or full URL obtained from a resource
     * @return mixed
     */
    public function get($entrypoint)
    {
        return $this->makeAPIRequest('GET', $entrypoint);
    }

    /**
     * General purpose 'POST' request against API
     * @param $entrypoint string contains either an @entrypoint or full URL obtained from a resource
     * @param $json string contains JSON formatted data to post
     * @return mixed
     */
    public function post($entrypoint, $json)
    {
        return $this->makeAPIRequest('POST', $entrypoint, $json);
    }

    /**
     * General purpose 'PUT' request against API
     * @param $entrypoint string contains either an @entrypoint or full URL obtained from a resource
     * @return mixed string contains JSON formatted data to put
     */
    public function put($entrypoint, $json)
    {
        return $this->makeAPIRequest('PUT', $entrypoint, $json);
    }

    /**
     * @param $method
     * @param $entrypoint
     * @param null $json
     * @return \stdClass object containing JSON decoded response - note this can be an error response for normally
     *         handled errors
     * @throws LogicException for integration errors, e.g. not setting a cache
     * @throws APIException for unexpected API failures
     */
    protected function makeAPIRequest($method, $entrypoint, $json = null)
    {
        $this->log->debug('{method} {entrypoint} {json}', [
            'method' => $method,
            'entrypoint' => $entrypoint,
            'json' => $json
        ]);
        $url = $this->resolveEntryPoint($entrypoint);
        $client = $this->getClient();


        $headers = ['Accept' => 'application/json'];
        if (!empty($json)) {
            $headers['Content-Type'] = 'application/json';
        }

        $request = new Request($method, $url, $headers, $json);

        try {
            $response = $client->send($request);

            $this->log->debug('{method} {entrypoint} succeeded {status}', [
                'method' => $method,
                'entrypoint' => $entrypoint,
                'status' => $response->getStatusCode(),
            ]);
        } catch (RequestException $e) {
            //we usually have a response available, but it's not guaranteed
            $response = $e->getResponse();
            $this->log->error(
                '{method} {entrypoint} {json} failed ({status}): {body}',
                [
                    'method' => $method,
                    'json' => $json,
                    'entrypoint' => $entrypoint,
                    'status' => $response ? $response->getStatusCode() : 0,
                    'body' => $response ? $response->getBody() : ''
                ]
            );

            throw new APIException("$method $entrypoint request failed", $e->getCode(), $e);
        } catch (GuzzleException $e) {
            $this->log->critical(
                '{method} {entrypoint} {json} failed',
                [
                    'method' => $method,
                    'json' => $json,
                    'entrypoint' => $entrypoint,
                ]
            );

            throw new APIException("$method $entrypoint failed", 0, $e);
        }

        $payload = json_decode($response->getBody());
        return $payload;
    }

    public function resolveEntryPoint($nameOrUrl)
    {
        if ($nameOrUrl[0] === '@') {
            $resolved = $this->getEntryPoint($nameOrUrl);
            $this->log->debug(
                'Entrypoint {entrypoint} resolves to {url}',
                ['entrypoint' => $nameOrUrl, 'url' => $resolved]
            );
            return $resolved;
        }
        //it's a URL
        return $nameOrUrl;
    }

    public function getEntryPoint($name)
    {
        if (!is_array($this->entrypoint)) {
            $this->entrypoint = $this->getEntrypointResource();
        } else {
            $this->log->debug('using previously loaded entrypoint');
        }

        if (!isset($this->entrypoint->_links->$name->href)) {
            throw new LogicException("Invalid LibLynx API entrypoint $name requested");
        }

        return $this->entrypoint->_links->$name->href;
    }

    protected function getEntrypointResource()
    {
        $entrypointResource = null;
        $key = 'entrypoint' . $this->clientId;

        $cache = $this->getCache();
        if ($cache->has($key)) {
            $this->log->debug('loading entrypoint from persistent cache');
            $entrypointResource = $cache->get($key);
            ;
        } else {
            $this->log->debug('entrypoint not cached, requesting from API');
            $entrypointResource = $this->get('api');
            $cache->set($key, $entrypointResource, 86400);
            $this->log->info('entrypoint loaded from API and cached');
        }


        return $entrypointResource;
    }

    public function getCache()
    {
        if (is_null($this->cache)) {
            throw new LogicException('LibLynx Connect Client requires a PSR-16 compatible cache');
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
            throw new LogicException('Cannot make API calls until setCredentials has been called');
        }
        if (!is_object($this->guzzle)) {
            $this->guzzle = $this->httpClientFactory->create(
                $this->apiroot,
                $this->clientId,
                $this->clientSecret,
                $this->getCache()
            );
        }

        return $this->guzzle;
    }
}
