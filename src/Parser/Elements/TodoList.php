<?php

namespace Dml\Parser\Elements;

class TodoList extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::TodoList;
    }
}
