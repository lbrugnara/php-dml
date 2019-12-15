<?php

namespace Dml;

class LexerContext
{
    const Normal = 0;

    /**
     * @var array
     */
    public $state;

    /**
     * @var bool
     */
    public $peek;

    public function __construct(bool $peek = false)
    {
        $this->state = [ self::Normal ];
        $this->peek = $peek;
    }
}
