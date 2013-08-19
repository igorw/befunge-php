<?php

namespace igorw\befunge;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** @api */
function execute($code, LoggerInterface $logger = null)
{
    $vm = new Machine($code, $logger);
    return $vm->execute();
}

function parse($raw_code)
{
    $code = [];

    $raw_code = preg_replace('#\r\n?#', "\n", $raw_code);
    $lines = explode("\n", $raw_code);
    foreach ($lines as $i => $line)
        for ($j = 0; $j < strlen($line); $j++)
            $code[$i][$j] = ord($line[$j]);

    return $code;
}

class Machine
{
    public $ip = [0, 0];
    public $storage_offset = [0, 0];
    public $delta = [1, 0];

    public $stack = [];
    public $code = [];
    public $string_mode = false;
    public $comment_mode = false;

    private $logger;

    function __construct($code, LoggerInterface $logger = null)
    {
        $this->code = parse($code);
        $this->logger = $logger ?: new NullLogger();
    }

    function execute()
    {
        while (true) {
            if (!$this->current_cell_exists()) {
                $this->wrap();
            }

            $cell = $this->current_cell();

            if ($this->string_mode && $cell !== '"') {
                $this->push(ord($cell));
                goto next;
            }

            if ($this->comment_mode && $cell !== ';') {
                goto next;
            }

            switch ($cell) {
                case '"':
                    $this->string_mode = !$this->string_mode;
                    break;
                case ';':
                    $this->comment_mode = !$this->comment_mode;
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
                    echo chr($this->pop());
                    break;
                case '.':
                    echo $this->pop();
                    break;
                case '&':
                    $this->push((int) fgets(STDIN));
                    break;
                case '~':
                    $this->push(ord(fread(STDIN, 1)));
                    break;
                case '!':
                    $this->push(!$this->pop());
                    break;
                case '\\':
                    $b = $this->pop();
                    $a = $this->pop();
                    $this->push($b);
                    $this->push($a);
                    break;
                case '$':
                    $this->pop();
                    break;
                case '[':
                    $this->turn_left();
                    break;
                case ']':
                    $this->turn_right();
                    break;
                case 'w':
                    $b = $this->pop();
                    $a = $this->pop();
                    if ($a < $b)
                        $this->turn_left();
                    else if ($a > $b)
                        $this->turn_right();
                    else
                        ; // noop
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

    private function turn_left()
    {
        $matrix = [
            [[1, 0], [0, -1]],
            [[0, -1], [-1, 0]],
            [[-1, 0], [0, 1]],
            [[0, 1], [1, 0]],
        ];

        $this->update_delta($matrix);
    }

    private function turn_right()
    {
        $matrix = [
            [[1, 0], [0, 1]],
            [[0, 1], [-1, 0]],
            [[-1, 0], [0, -1]],
            [[0, -1], [1, 0]],
        ];

        $this->update_delta($matrix);
    }

    private function update_delta(array $matrix)
    {
        foreach ($matrix as $entry) {
            list($current, $next) = $entry;
            if ($current === $this->delta) {
                $this->delta = $next;
                return;
            }
        }

        throw new \RuntimeException('Delta in inconsistent state');
    }

    private function current_cell_exists()
    {
        list($x, $y) = $this->ip;
        return isset($this->code[$y][$x]);
    }

    private function current_cell()
    {
        list($x, $y) = $this->ip;
        return chr($this->code[$y][$x]);
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
