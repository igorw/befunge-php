<?php

namespace igorw\befunge;

/** @api */
function execute($code)
{
    $vm = new Machine($code);
    return $vm->execute();
}

class Machine
{
    public $ip = [0, 0];
    public $storage_offset = [0, 0];
    public $delta = [1, 0];

    public $stack = [];
    public $code = [];
    public $string_mode = false;

    function __construct($code)
    {
        $this->load($code);
    }

    function execute()
    {
        while (true) {
            if (!$this->current_cell_exists()) {
                $this->wrap();
            }

            $cell = $this->current_cell();

            if ($this->string_mode && $cell !== '"') {
                $this->push($cell);
                goto next;
            }

            switch ($cell) {
                case '"':
                    $this->string_mode = !$this->string_mode;
                    break;
                case '>':
                    $this->delta = [1, 0];
                    break;
                case '<':
                    $this->delta = [-1, 0];
                    break;
                case '^':
                    $this->delta = [0, -1];
                    break;
                case 'v':
                    $this->delta = [0, 1];
                    break;
                case ':':
                    $value = $this->pop();
                    $this->push($value);
                    $this->push($value);
                    break;
                case '#':
                    $this->next();
                    break;
                case ',':
                    echo $this->pop();
                    break;
                case '.':
                    echo $this->pop();
                    break;
                case '&':
                    $this->push((int) fread(STDIN, 1));
                    break;
                case '~':
                    $this->push(fread(STDIN, 1));
                    break;
                case '!':
                    $this->push(!$this->pop());
                    break;
                case '_':
                    $cond = $this->pop();
                    if ($cond)
                        $this->delta = [-1, 0];
                    else
                        $this->delta = [1, 0];
                    break;
                case 'g':
                    list($dx, $dy) = $this->storage_offset;
                    $y = $this->pop() + $dy;
                    $x = $this->pop() + $dx;
                    $this->push($this->code[$y][$x]);
                    break;
                case 'p':
                    list($dx, $dy) = $this->storage_offset;
                    $y = $this->pop() + $dy;
                    $x = $this->pop() + $dx;
                    $value = $this->pop();
                    $this->code[$y][$x] = $value;
                    break;
                case '+':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($a + $b);
                    break;
                case '-':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($a - $b);
                    break;
                case '*':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($a * $b);
                    break;
                case '/':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($a / $b);
                    break;
                case '%':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($a % $b);
                    break;
                case '@':
                    return 0;
                    break;
                case ' ':
                    // noop
                    break;
                default:
                    if (preg_match('#^\d$#', $cell)) {
                        $this->push((int) $cell);
                        goto next;
                    }

                    if (preg_match('#^[abcdef]$#', $cell)) {
                        $mapping = ['a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15];
                        $this->push($mapping[$cell]);
                        goto next;
                    }

                    throw new \RuntimeException(sprintf('Invalid instruction: %s', $cell));
                    break;
            }

            next: {
                $this->next();
            }
        }
    }

    private function load($code)
    {
        $code = preg_replace('#\r\n?#', "\n", $code);
        $lines = explode("\n", $code);
        $this->code = $lines;
    }

    private function next()
    {
        $this->ip[0] += $this->delta[0];
        $this->ip[1] += $this->delta[1];
    }

    private function invert_delta()
    {
        $this->delta[0] *= -1;
        $this->delta[1] *= -1;
    }

    private function current_cell_exists()
    {
        list($x, $y) = $this->ip;
        return isset($this->code[$y][$x]);
    }

    private function current_cell()
    {
        list($x, $y) = $this->ip;
        return $this->code[$y][$x];
    }

    private function wrap()
    {
        $this->invert_delta();
        $this->next();

        while ($this->current_cell_exists())
            $this->next();

        $this->invert_delta();
        $this->next();
    }

    private function push($value)
    {
        array_push($this->stack, $value);
    }

    private function pop()
    {
        return array_pop($this->stack);
    }
}
