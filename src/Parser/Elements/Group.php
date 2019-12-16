<?php

namespace Dml\Parser\Elements;

class Group extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Group;
    }
}
