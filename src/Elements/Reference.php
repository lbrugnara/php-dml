<?php

namespace Dml\Elements;

class Reference extends Element
{
    /**
     * @var string
     */
    public $refid;

    public function __construct(string $id)
    {
        parent::__construct();
        $this->type = Element::Reference;
        $this->refid = $id;
    }
}
