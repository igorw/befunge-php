# befunge-php

Befunge is an esoteric programming language that operates in a 2d space, allowing the instruction pointer (ip) to point in any direction.

This interpreter is based on the funge-98 spec, and implements a non-concurrent two-dimensional variant of the language.

## Example

Hello world:

    0"!dlroW ,olleH">:#,_@

Count:

    v                                        @
    > & 00p 0 10p ;; 0. ;; " ", ;; > 00g 10g w @
    v                                        <
    > 10g 1+ 10p ;; 10g. ;; " ",   ^

## Usage

Just pass a filename to the interpreter:

    $ bin/befunge examples/hello.b98

You can also pass the `--debug` option to get a log of all instructions.

    $ bin/befunge --debug examples/hello.b98

## Stability

Highly experimental, unstable, incomplete. PRs welcome.

## References

* [Funge-98 spec](http://catseye.tc/projects/funge98/doc/funge98.html)
* [Befunge on Esolang](http://esolangs.org/wiki/Befunge)
