<?php

namespace igorw\befunge;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    function linesShouldBePaddedToTheLongest()
    {
        $lines = [
            'v',
            '>    v',
            'v    <',
            '@',
        ];
        $expected = [
            'v     ',
            '>    v',
            'v    <',
            '@     ',
        ];
        $this->assertSame($expected, pad_lines_to_longest($lines));
    }
}
