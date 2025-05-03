<?php

namespace Sansec\Shield\Test\Model;

class SerializerTest extends \PHPUnit\Framework\TestCase
{
    public function testNoExceptionWithInvalidUtf8()
    {
        $serializer = new \Sansec\Shield\Model\Serializer();
        $result = $serializer->serialize(['key' => "\xB1\x31"]);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertIsString($result);
    }
}
