<?php

namespace LibLynx\Connect\Resource;

/**
 * Provides a simple wrapper around a LibLynx identification resource
 * @package LibLynx\Connect
 *
 * @property \stdClass $id
 * @property \stdClass $ip
 * @property \stdClass $url
 * @property \stdClass $user_agent
 * @property \stdClass $account
 * @property \stdClass $status
 */
class Identification extends AbstractResource
{
    public function isIdentified(): bool
    {
        return $this->status == 'identified';
    }

    public function requiresWayf(): bool
    {
        return $this->status == 'wayf';
    }

    public function limitReached(): bool
    {
        return $this->status == 'concurrency-denial';
    }

    public function getWayfUrl(): string
    {
        return $this->getLink('wayf');
    }

    public function hasAuthorization(string $unitCode, string $rights = 'view'): bool
    {
        if (isset($this->authorizations[$unitCode][$rights])) {
            return $this->authorizations[$unitCode][$rights] == 'authorized';
        }
        return false;
    }
}
