<?php

namespace igorw\befunge;

class VmTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider provideCodeSamples */
    function testExecute($expected, $code)
    {
        $code = is_array($code) ? implode("\n", $code) : $code;
        $this->expectOutputString($expected);
        $this->assertSame(0, execute($code));
    }

    function provideCodeSamples()
    {
        return [
            'hello world' => [
                'Hello, World!',
                '0"!dlroW ,olleH">:#,_@',
            ],
            'hello world 2d' => [
                'Hello, World!',
                [
                    '"!dlroW ,olleH":v ',
                    '             v:,_@',
                    '             >  ^ ',
                ],
            ],
            'quine' => [
                '01->1# +# :# 0# g# ,# :# 5# 8# *# 4# +# -# _@',
                '01->1# +# :# 0# g# ,# :# 5# 8# *# 4# +# -# _@',
            ],
            'comments' => [
                '1',
                '0 ; foo bar baz ; 1 ; qux quux ; + ; output ; . ; exit ; @',
            ],
            'greater than' => [
                '1',
                '43`.@',
            ],
            'vertical cond true' => [
                '1',
                [
                    'v >1.@',
                    '>1|   ',
                    '  >0.@',
                ],
            ],
            'vertical cond false' => [
                '0',
                [
                    'v >1.@',
                    '>0|   ',
                    '  >0.@',
                ],
            ],
        ];
    }
}
