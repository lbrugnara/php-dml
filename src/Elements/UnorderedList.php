<?php

namespace Dml\Elements;

class UnorderedList extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::UnorderedList;
    }
}
