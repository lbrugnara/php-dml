<?php

namespace Dml\Parser\Elements;

class ReferenceGroup extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ReferenceGroup;
    }
}
