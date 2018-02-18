<?php

namespace LibLynx\Connect;

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
class Identification extends LibLynxResource
{
    public function isIdentified()
    {
        return $this->status == 'identified';
    }

    public function requiresWayf()
    {
        return $this->status == 'wayf';
    }

    public function getWayfUrl()
    {
        return $this->getLink('wayf');
    }
}
