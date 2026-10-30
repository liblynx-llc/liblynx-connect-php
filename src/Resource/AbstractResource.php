<?php

namespace LibLynx\Connect\Resource;

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
abstract class AbstractResource
{
    /** @var array<mixed> */
    private array $data = [];

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getJson(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        throw new LogicException("No value called $name");
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function getLink(string $name): string
    {
        if (isset($this->data['_links'][$name])) {
            return $this->data['_links'][$name]['href'];
        }
        throw new LogicException("resource did not contain a ".$name." link");
    }

    public function hasLink(string $name): bool
    {
        return isset($this->data['_links'][$name]);
    }
}
