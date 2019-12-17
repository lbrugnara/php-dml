<?php

namespace Dml\Output\Html\Tags;

class Preformatted extends Tag
{
    public function __construct()
    {
        parent::__construct("pre", true);
    }
}