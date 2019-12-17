<?php

namespace Dml\Output\Html\Tags;

class OrderedList extends Tag
{
    public function __construct()
    {
        parent::__construct("ol", true);
    }
}