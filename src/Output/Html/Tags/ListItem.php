<?php

namespace Dml\Output\Html\Tags;

class ListItem extends Tag
{
    public function __construct()
    {
        parent::__construct("li", true);
    }
}