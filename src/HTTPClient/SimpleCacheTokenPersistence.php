<?php

namespace LibLynx\Connect\HTTPClient;

use kamermans\OAuth2\Persistence\TokenPersistenceInterface;
use kamermans\OAuth2\Token\TokenInterface;
use Psr\SimpleCache\CacheInterface;

class SimpleCacheTokenPersistence implements TokenPersistenceInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $cacheKey;

    public function __construct(CacheInterface $cache, $cacheKey = 'guzzle-oauth2-token')
    {
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    public function saveToken(TokenInterface $token)
    {
        $this->cache->set($this->cacheKey, $token->serialize());
    }

    public function restoreToken(TokenInterface $token)
    {
        $data = $this->cache->get($this->cacheKey);

        if (!is_array($data)) {
            return null;
        }

        return $token->unserialize($data);
    }

    public function deleteToken()
    {
        $this->cache->delete($this->cacheKey);
    }

    public function hasToken()
    {
        $this->cache->has($this->cacheKey);
    }
}
