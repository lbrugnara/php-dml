<?php

namespace Dml\Parser\Elements;

class ListItem extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ListItem;
    }
}
