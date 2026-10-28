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
        $id->ip = $vars['REMOTE_ADDR'] ?? null;
        $id->referrer = $vars['HTTP_REFERER'] ?? null;

        //figure out protocol
        $protocol = 'http';
        if (isset($vars['HTTPS']) && $vars['HTTPS'] === 'on') {
            $protocol = 'https';
        } elseif (isset($vars['HTTP_X_FORWARDED_PROTO']) && $vars['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https';
        }

        $id->url = $protocol . "://" . $vars['HTTP_HOST'] . $vars['REQUEST_URI'];
        $id->user_agent = $vars['HTTP_USER_AGENT'] ?? null;
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
