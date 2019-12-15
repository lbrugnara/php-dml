<?php

namespace Dml\Elements;

class ListItem extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ListItem;
    }
}
