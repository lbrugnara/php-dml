<?php

namespace Dml\Elements;

class Blockquote extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Blockquote;
    }
}
