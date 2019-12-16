<?php

namespace Dml\Parser\Elements;

class ThematicBreak extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ThematicBreak;
    }
}
