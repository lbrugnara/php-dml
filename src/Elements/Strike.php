<?php

namespace Dml\Elements;

class Strike extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Strike;
    }
}
