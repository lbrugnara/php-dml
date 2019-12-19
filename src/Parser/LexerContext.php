<?php

namespace Dml\Parser;

class LexerContext
{
    const Markup    = 0x0;
    const Text      = 0x1;

    /**
     * @var array
     */
    private $state;

    /**
     * State array index
     *
     * @var int
     */
    private $index;

    public function __construct()
    {
        $this->state = [ self::Markup ];
        $this->index = 0;
    }

    public function state() : int
    {
        return $this->state[$this->index];
    }

    public function pushState(int $state) : void
    {
        $this->state[] = $state;
        $this->index++;
    }

    public function popState() : int
    {
        $this->index--;
        return array_pop($this->state);
    }
}
