<?php

namespace Dml\Parser\Elements;

class Italic extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Italic;
    }
}
