<?php

namespace Dml\Elements;

class Group extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Group;
    }
}
