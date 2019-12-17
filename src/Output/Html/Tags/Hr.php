<?php

namespace Dml\Output\Html\Tags;

class Hr extends Tag
{
    public function __construct()
    {
        parent::__construct("hr", false);
    }
}