<?php

namespace Dml\Elements;

class TodoListItem extends Element
{
    /**
     * @var bool
     */
    public $completed;

    public function __construct(bool $completed)
    {
        parent::__construct();
        $this->type = Element::ListItem;
        $this->completed = $completed;
    }
}
