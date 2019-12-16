<?php

namespace Dml\Parser\Elements;

class ReferenceGroup extends Element
{
    /**
     * @var \Dml\Parser\Elements\ReferenceLink[]
     */
    public $links;

    public function __construct()
    {
        parent::__construct();
        $this->type = Element::ReferenceGroup;
    }
}
