<?php

namespace Dml\Output\Html\Tags;

class Header extends Tag
{
    /**
     * Header type
     *
     * @var string
     */
    private $type;

    public function __construct(string $type)
    {
        parent::__construct($type, true);
    }
}