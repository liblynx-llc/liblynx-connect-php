<?php

namespace LibLynx\Connect;

use LibLynx\Connect\Exception\LogicException;
use PHPUnit\Framework\TestCase;

class IdentificationTest extends TestCase
{
    public function testIdentifiedResponse()
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');

        $id = new Identification(json_decode($json));
        $this->assertFalse($id->requiresWayf());
        $this->assertTrue($id->isIdentified());
    }

    public function testWAYFResponse()
    {
        $json = file_get_contents(__DIR__ . '/assets/identification2.json');

        $id = new Identification(json_decode($json));
        $this->assertTrue($id->requiresWayf());
        $this->assertFalse($id->isIdentified());
        $this->assertEquals('http://172.17.8.101/wayf/1fd03a8e5d4dbad4146a38ba15db82c7', $id->getWayfUrl());
    }

    /**
     * @expectedException LogicException
     */
    public function testBadWayfAttempt()
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        $id = new Identification(json_decode($json));
        $this->assertFalse($id->hasLink('wayf'));
        $id->getWayfUrl();
    }

    /**
     * @expectedException LogicException
     */
    public function testMissingData()
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        $id = new Identification(json_decode($json));
        $this->assertEmpty($id->banjo);
    }
}
