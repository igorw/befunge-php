<?php

namespace igorw\befunge;

class VmTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    function helloWorld()
    {
        $this->expectOutputString('Hello, World!');
        $code = '0"!dlroW ,olleH">:#,_@';
        $this->assertSame(0, execute($code));
    }
}
