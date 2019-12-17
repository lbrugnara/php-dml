<?php

namespace Dml\Output\Html\Tags;

class Italic extends Tag
{
    public function __construct()
    {
        parent::__construct("i", true);
    }
}