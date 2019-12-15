<?php

namespace Dml\Elements;

class Custom extends Element
{
    /**
     * @var string
     */
    public $name;

    public function __construct(string $name)
    {
        parent::__construct();
        $this->name = $name;
        $this->type = Element::Custom;
    }
}
