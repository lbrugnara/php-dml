<?php

namespace Dml\Parser\Elements;

class Document extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Document;
    }
}
