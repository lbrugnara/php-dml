<?php

namespace Dml\Parser\Elements;

class Strong extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Strong;
    }
}
