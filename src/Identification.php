<?php

namespace LibLynx\Connect;

/**
 * Provides a simple wrapper around a LibLynx identification resource
 * @package LibLynx\Connect
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

    public function doWayfRedirect()
    {
        $url = $this->getWayfUrl();
        if (!is_null($url)) {
            header("Location: $url");
            exit;
        }
    }
}
