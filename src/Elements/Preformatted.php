<?php

namespace Dml\Elements;

class Preformatted extends Element
{
    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Preformatted;
    }
}
