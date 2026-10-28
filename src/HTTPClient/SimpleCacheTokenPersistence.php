<?php

namespace LibLynx\Connect\HTTPClient;

use kamermans\OAuth2\Persistence\TokenPersistenceInterface;
use kamermans\OAuth2\Token\Serializable;
use kamermans\OAuth2\Token\TokenInterface;
use Psr\SimpleCache\CacheInterface;

class SimpleCacheTokenPersistence implements TokenPersistenceInterface
{
    private CacheInterface $cache;

    private string $cacheKey;

    public function __construct(CacheInterface $cache, string $cacheKey = 'guzzle-oauth2-token')
    {
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    /**
     * @return void
     */
    public function saveToken(TokenInterface $token)
    {
        if ($token instanceof Serializable) {
            $this->cache->set($this->cacheKey, $token->serialize());
        } else {
            throw new \RuntimeException('Unable to store unserializable token');
        }
    }

    /**
     * @return ?TokenInterface
     */
    public function restoreToken(TokenInterface $token)
    {
        $data = $this->cache->get($this->cacheKey);

        if (!is_array($data)) {
            return null;
        }

        if ($token instanceof Serializable) {
            return $token->unserialize($data);
        } else {
            throw new \RuntimeException('Unable to restore unserializable token');
        }
    }

    /**
     * @return void
     */
    public function deleteToken()
    {
        $this->cache->delete($this->cacheKey);
    }

    /**
     * Returns true if a token exists (although it may not be valid)
     *
     * @return bool
     */
    public function hasToken()
    {
        return $this->cache->has($this->cacheKey);
    }
}
