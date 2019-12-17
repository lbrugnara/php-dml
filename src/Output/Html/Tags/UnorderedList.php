<?php

namespace Dml\Output\Html\Tags;

class UnorderedList extends Tag
{
    public function __construct()
    {
        parent::__construct("ul", true);
    }
}