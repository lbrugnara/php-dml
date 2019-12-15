<?php

namespace Dml\Elements;

class ReferenceGroup extends Element
{
    /**
     * @var \Dml\Elements\ReferenceLink[]
     */
    public $links;

    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ReferenceGroup;
    }
}
