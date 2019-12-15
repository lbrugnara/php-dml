<?php

namespace Dml\Elements;

class OrderedList extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::OrderedList;
    }
}
