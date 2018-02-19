<?php

namespace LibLynx\Connect;

use LibLynx\Connect\Exception\LogicException;
use PHPUnit\Framework\TestCase;

class IdentificationRequestTest extends TestCase
{
    public function testFromArray()
    {
        $server=[
            'REMOTE_ADDR' => '1.1.1.1',
            'HTTP_REFERER' => 'http://example.com/referer',
            'REQUEST_URI' => 'http://example.com/uri',
            'HTTP_USER_AGENT' => 'test',
        ];

        $req = IdentificationRequest::fromArray($server);
        $this->assertEquals('1.1.1.1', $req->ip);
        $this->assertEquals('http://example.com/referer', $req->referrer);
        $this->assertEquals('http://example.com/uri', $req->url);
        $this->assertEquals('test', $req->user_agent);

        $json = $req->getRequestJSON();
        $data = json_decode($json, true);
        $this->assertCount(4, $data);
        $this->assertArrayHasKey('ip', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('user_agent', $data);
        $this->assertArrayHasKey('referrer', $data);
    }
}
