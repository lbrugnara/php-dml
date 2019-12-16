<?php

namespace Dml\Parser\Elements;

class Preformatted extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Preformatted;
    }
}
