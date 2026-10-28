<?php

namespace LibLynx\Connect;

use LibLynx\Connect\Exception\LogicException;
use LibLynx\Connect\Resource\Identification;
use PHPUnit\Framework\TestCase;

class IdentificationTest extends TestCase
{
    public function testIdentifiedResponse(): void
    {
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');

        $id = new Identification(json_decode($json, true));
        $this->assertFalse($id->requiresWayf());
        $this->assertTrue($id->isIdentified());
    }

    public function testWAYFResponse(): void
    {
        $json = file_get_contents(__DIR__ . '/assets/identification2.json');

        $id = new Identification(json_decode($json, true));
        $this->assertTrue($id->requiresWayf());
        $this->assertFalse($id->isIdentified());
        $this->assertEquals('http://172.17.8.101/wayf/1fd03a8e5d4dbad4146a38ba15db82c7', $id->getWayfUrl());
    }

    public function testBadWayfAttempt(): void
    {
        $this->expectException(LogicException::class);
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        $id = new Identification(json_decode($json, true));
         $this->assertFalse($id->hasLink('wayf'));
        $id->getWayfUrl();
    }

    public function testMissingData(): void
    {
        $this->expectException(LogicException::class);
        $json = file_get_contents(__DIR__ . '/assets/identification1.json');
        $id = new Identification(json_decode($json, true));
        $this->assertEmpty($id->__get('banjo'));
    }
}
