<?php

namespace LibLynx\Connect;

/**
 * Provides a simple way to construct a LibLynx identification request
 * @package LibLynx\Connect
 */
class IdentificationRequest
{
    public $url;
    public $referrer;
    public $ip;
    public $user_agent;

    /**
     * This can be used to rapidly construct a request from superglobals simply by calling
     *
     * $req = class IdentificationRequest::fromArray($_SERVER);
     * @param $vars
     * @return IdentificationRequest
     */
    public static function fromArray($vars)
    {
        $id = new IdentificationRequest;
        if (isset($vars['REMOTE_ADDR'])) {
            $id->ip = $vars['REMOTE_ADDR'];
        }
        if (isset($vars['HTTP_REFERER'])) {
            $id->referrer = $vars['HTTP_REFERER'];
        }
        if (isset($vars['REQUEST_URI'])) {
            $id->url = $vars['REQUEST_URI'];
        }
        if (isset($vars['HTTP_USER_AGENT'])) {
            $id->user_agent = $vars['HTTP_USER_AGENT'];
        }
        return $id;
    }

    public function getRequestJSON()
    {
        $json = [];
        $allowed = ['url', 'referrer', 'user_agent', 'ip'];
        foreach ($allowed as $name) {
            if (isset($this->$name) && !empty($this->$name)) {
                $json[$name] = $this->$name;
            }
        }
        return json_encode($json);
    }
}
