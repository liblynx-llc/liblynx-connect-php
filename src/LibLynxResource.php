<?php

namespace LibLynx\Connect;

use LibLynx\Connect\Exception\LogicException;

/**
 * Class LibLynxResource provides a base class for all HAL-style resources retrieved from the LibLynx API.
 *
 * All JSON returned by the LibLynx API is modelled on the Hypertext Application Language
 * http://stateless.co/hal_specification.html
 *
 * @package LibLynx\Connect
 *
 * @property \stdClass $_links
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
        throw new LogicException("No value called $name");
    }

    /**
     * @param $name
     * @return mixed
     * @throws LogicException if link with name isn't present
     */
    public function getLink($name)
    {
        if (isset($this->_links->$name)) {
            return $this->_links->$name->href;
        }
        throw new LogicException("resource did not contain a ".$name." link");
    }

    public function hasLink($name)
    {
        return isset($this->_links->$name);
    }
}
