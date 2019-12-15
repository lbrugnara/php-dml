<?php

namespace Dml\Elements;

class Paragraph extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Paragraph;
    }
}
