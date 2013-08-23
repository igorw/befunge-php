<?php

namespace igorw\befunge;

class VmTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    function helloWorld()
    {
        $code = '0"!dlroW ,olleH">:#,_@';
        $this->expectOutputString('Hello, World!');
        $this->assertSame(0, execute($code));
    }

    /** @test */
    function helloWorld2d()
    {
        $code = implode("\n", [
            '"!dlroW ,olleH":v ',
            '             v:,_@',
            '             >  ^ ',
        ]);
        $this->expectOutputString('Hello, World!');
        $this->assertSame(0, execute($code));
    }

    /** @test */
    function quine()
    {
        $code = '01->1# +# :# 0# g# ,# :# 5# 8# *# 4# +# -# _@';
        $this->expectOutputString($code);
        $this->assertSame(0, execute($code));
    }

    /** @test */
    function comments()
    {
        $code = '0 ; foo bar baz ; 1 ; qux quux ; + ; output ; . ; exit ; @';
        $this->expectOutputString('1');
        $this->assertSame(0, execute($code));
    }

    /** @test */
    function isGreaterThan()
    {
        $code = '43`.@';
        $this->expectOutputString('1');
        $this->assertSame(0, execute($code));
    }
}
