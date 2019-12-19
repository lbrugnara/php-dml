<?php

namespace Dml\Parser\Elements;

class OrderedList extends Element
{
    const Numeric = 0;
    const UpperAlpha = 1;
    const LowerAlpha = 2;

    /**
     * List style
     *
     * @var int
     */
    public $style;

    public function __construct(int $style)
    {
        parent::__construct();
        $this->type = Element::OrderedList;
        $this->style = $style;
    }
}
