<?php

namespace LibLynx\Connect;

/**
 * Class LibLynxResource provides a base class for all HAL-style resources retrieved from the LibLynx API.
 *
 * All JSON returned by the LibLynx API is modelled on the Hypertext Application Language
 * http://stateless.co/hal_specification.html
 *
 * @package LibLynx\Connect
 */
abstract class LibLynxResource
{
    public function __construct($obj)
    {
        $vars = get_object_vars($obj);
        foreach ($vars as $name => $value) {
            $this->$name = $value;
        }
    }

    public function __get($name)
    {
        throw new \RuntimeException("No value called $name");
    }

    public function getLink($name)
    {
        if (isset($this->_links->$name)) {
            return $this->_links->$name->href;
        }
        return false;
    }
}
