<?php

namespace Dml\Parser\Elements;

class Underline extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Underlined;
    }
}
