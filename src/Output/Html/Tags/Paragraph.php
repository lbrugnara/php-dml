<?php

namespace Dml\Output\Html\Tags;

class Paragraph extends Tag
{
    public function __construct()
    {
        parent::__construct("p", true);
    }
}