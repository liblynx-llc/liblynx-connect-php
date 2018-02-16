<?php

namespace LibLynx\Connect;

/**
 * Provides a simple wrapper around a LibLynx identification resource
 * @package LibLynx\Connect
 */
class Identification
{
    public $url;
    public $referrer;
    public $ip;
    public $user_agent;

    public $status;

    public static function fromSuperglobals()
    {
        $id = new Identification;
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $id->ip = $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            $id->referrer = $_SERVER['HTTP_REFERER'];
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $id->url = $_SERVER['REQUEST_URI'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $id->user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        return $id;
    }

    public static function fromJSON($response)
    {
        $id = new Identification;
        $vars = get_object_vars($response);
        foreach ($vars as $name => $value) {
            $id->$name = $value;
        }
        return $id;
    }

    public function getRequestJSON()
    {
        $json=[];
        $allowed=['url', 'referrer', 'user_agent', 'ip'];
        foreach ($allowed as $name) {
            if (isset($this->$name) && !empty($this->$name)) {
                $json[$name]=$this->$name;
            }
        }
        return json_encode($json);
    }

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
        return (($this->status == 'wayf') && isset($this->_links->wayf->href)) ? $this->_links->wayf->href : null;
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
