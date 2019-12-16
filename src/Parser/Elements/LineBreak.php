<?php

namespace Dml\Parser\Elements;

class LineBreak extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::LineBreak;
    }
}
