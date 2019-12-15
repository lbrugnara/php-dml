<?php

namespace Dml\Elements;

class ThematicBreak extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ThematicBreak;
    }
}
