<?php

namespace LibLynx\Connect;

use kamermans\OAuth2\Token\RawToken;
use kamermans\OAuth2\Token\RawTokenFactory;
use LibLynx\Connect\HTTPClient\SimpleCacheTokenPersistence;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Simple\ArrayCache;
use PHPUnit\Framework\TestCase;

class SimpleCacheTokenPersistenceTest extends TestCase
{
    protected CacheInterface $cache;

    public function getInstance(): SimpleCacheTokenPersistence
    {
        return new SimpleCacheTokenPersistence($this->cache);
    }

    protected function setUp(): void
    {
        $arrayAdapter = new ArrayAdapter();
        $this->cache = new Psr16Cache($arrayAdapter);
    }

    public function testRestoreTokenCustomKey(): void
    {
        $simpleCache = new SimpleCacheTokenPersistence($this->cache, 'foo-bar');

        $factory = new RawTokenFactory();
        $token = $factory([
            'access_token' => 'abcdefghijklmnop',
            'refresh_token' => '0123456789abcdef',
            'expires_in' => 3600,
        ]);
        $simpleCache->saveToken($token);

        /** @var RawToken $restoredToken */
        $restoredToken = $simpleCache->restoreToken(new RawToken);
        $this->assertInstanceOf('\kamermans\OAuth2\Token\RawToken', $restoredToken);

        $tokenBefore = $token->serialize();
        $tokenAfter = $restoredToken->serialize();

        $this->assertEquals($tokenBefore, $tokenAfter);

        $simpleCache->deleteToken();
    }
}
