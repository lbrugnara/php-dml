<?php

namespace Dml\Output\Html\Tags;

class Blockquote extends Tag
{
    public function __construct()
    {
        parent::__construct("blockquote", true);
    }
}