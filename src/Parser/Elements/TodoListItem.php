<?php

namespace Dml\Parser\Elements;

class TodoListItem extends ListItem
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
