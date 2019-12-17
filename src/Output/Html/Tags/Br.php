<?php

namespace Dml\Output\Html\Tags;

class Br extends Tag
{
    public function __construct()
    {
        parent::__construct("br", false);
    }
}