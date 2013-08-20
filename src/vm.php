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

function parse($code)
{
    $space = [];

    $code = preg_replace('#\r\n?#', "\n", $code);
    $lines = explode("\n", $code);
    $lines = array_map('str_split', $lines);
    foreach ($lines as $i => $line)
        foreach ($line as $j => $char)
            $space[$i][$j] = ord($char);

    return $space;
}

const MACHINE_HALT = 0;
const MACHINE_CONTINUE = 1;

class Machine
{
    public $ip = [0, 0];
    public $storage_offset = [0, 0];
    public $delta = [1, 0];

    public $stack = [];
    public $space = [];
    public $string_mode = false;
    public $comment_mode = false;

    private $logger;
    private $prev_stack = [];

    function __construct($code, LoggerInterface $logger = null)
    {
        $this->space = parse($code);
        $this->logger = $logger ?: new NullLogger();
    }

    function execute()
    {
        $this->logger->debug('execute', ['stack' => $this->stack]);

        while (true) {
            if (!$this->current_cell_exists()) {
                $this->wrap();
            }

            $cell = $this->current_cell();
            $status = $this->process_operation($cell);

            if (MACHINE_HALT === $status)
                return 0;

            $this->next();
        }
    }

    private function process_operation($cell)
    {
        if ($this->string_mode && $cell !== '"') {
            $this->logger->debug('push char', ['char' => $cell]);
            $this->push(ord($cell));
            return MACHINE_CONTINUE;
        }

        if ($this->comment_mode && $cell !== ';') {
            return MACHINE_CONTINUE;
        }

        switch ($cell) {
            case '"':
                $this->string_mode = !$this->string_mode;
                $this->logger->debug('toggle string mode', ['string_mode' => $this->string_mode]);
                break;
            case ';':
                $this->comment_mode = !$this->comment_mode;
                $this->logger->debug('toggle comment mode', ['comment_mode' => $this->comment_mode]);
                break;
            case '>':
                $this->delta = [1, 0];
                $this->logger->debug('direction right');
                break;
            case '<':
                $this->delta = [-1, 0];
                $this->logger->debug('direction left');
                break;
            case '^':
                $this->delta = [0, -1];
                $this->logger->debug('direction up');
                break;
            case 'v':
                $this->delta = [0, 1];
                $this->logger->debug('direction down');
                break;
            case ':':
                $value = $this->pop();
                $this->push($value);
                $this->push($value);
                $this->logger->debug('duplicate', ['value' => $value]);
                break;
            case '#':
                $this->next();
                $this->logger->debug('discard');
                break;
            case ',':
                $char = chr($this->pop());
                echo $char;
                $this->logger->debug('output char', ['char' => $char]);
                break;
            case '.':
                $number = $this->pop();
                echo $number;
                $this->logger->debug('output number', ['number' => $number]);
                break;
            case '&':
                $number = (int) fgets(STDIN);
                $this->push($number);
                $this->logger->debug('input number', ['number' => $number]);
                break;
            case '~':
                $char = fread(STDIN, 1);
                $this->push(ord($char));
                $this->logger->debug('input char', ['char' => $char]);
                break;
            case '!':
                $this->push(!$this->pop());
                $this->logger->debug('negate');
                break;
            case '\\':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($b);
                $this->push($a);
                $this->logger->debug('swap');
                break;
            case '$':
                $value = $this->pop();
                $this->logger->debug('discard', ['value' => $value]);
                break;
            case '[':
                $this->turn_left();
                $this->logger->debug('turn left');
                break;
            case ']':
                $this->turn_right();
                $this->logger->debug('turn right');
                break;
            case 'w':
                $b = $this->pop();
                $a = $this->pop();
                if ($a < $b) {
                    $this->turn_left();
                    $this->logger->debug('compare: turn left');
                } else if ($a > $b) {
                    $this->turn_right();
                    $this->logger->debug('compare: turn right');
                } else {
                    // noop
                    $this->logger->debug('compare: keep going');
                }
                break;
            case '_':
                $cond = $this->pop();
                if ($cond) {
                    $this->delta = [-1, 0];
                    $this->logger->debug('horizontal cond: direction left');
                } else {
                    $this->delta = [1, 0];
                    $this->logger->debug('horizontal cond: direction right');
                }
                break;
            case 'g':
                list($dx, $dy) = $this->storage_offset;
                $y = $this->pop() + $dy;
                $x = $this->pop() + $dx;
                $value = $this->space[$y][$x];
                $this->push($value);
                $this->logger->debug('get', ['x' => $x, 'y' => $y, 'value' => $value]);
                break;
            case 'p':
                list($dx, $dy) = $this->storage_offset;
                $y = $this->pop() + $dy;
                $x = $this->pop() + $dx;
                $value = $this->pop();
                $this->space[$y][$x] = $value;
                $this->logger->debug('put', ['x' => $x, 'y' => $y, 'value' => $value]);
                break;
            case '+':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($a + $b);
                $this->logger->debug('+', ['a' => $a, 'b' => $b]);
                break;
            case '-':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($a - $b);
                $this->logger->debug('-', ['a' => $a, 'b' => $b]);
                break;
            case '*':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($a * $b);
                $this->logger->debug('*', ['a' => $a, 'b' => $b]);
                break;
            case '/':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($a / $b);
                $this->logger->debug('/', ['a' => $a, 'b' => $b]);
                break;
            case '%':
                $b = $this->pop();
                $a = $this->pop();
                $this->push($a % $b);
                $this->logger->debug('%', ['a' => $a, 'b' => $b]);
                break;
            case '@':
                $this->logger->debug('exit');
                return MACHINE_HALT;
                break;
            case ' ':
                // noop
                break;
            default:
                if (preg_match('#^\d$#', $cell)) {
                    $this->push((int) $cell);
                    $this->logger->debug('push', ['value' => (int) $cell]);
                    break;
                }

                if (preg_match('#^[abcdef]$#', $cell)) {
                    $mapping = ['a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15];
                    $this->push($mapping[$cell]);
                    $this->logger->debug('push', ['value' => $mapping[$cell]]);
                    break;
                }

                throw new \RuntimeException(sprintf('Invalid instruction: %s', $cell));
                break;
        }

        return MACHINE_CONTINUE;
    }

    private function next()
    {
        $this->ip[0] += $this->delta[0];
        $this->ip[1] += $this->delta[1];

        if ($this->prev_stack !== $this->stack)
            $this->logger->debug('next', ['stack' => $this->stack]);
        $this->prev_stack = $this->stack;
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
        return isset($this->space[$y][$x]);
    }

    private function current_cell()
    {
        list($x, $y) = $this->ip;
        return chr($this->space[$y][$x]);
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
