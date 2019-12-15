<?php

namespace Dml\Elements;

class Code extends Element
{
    /**
     * @var bool
     */
    public $isBlock;

    public function __construct(bool $isBlock)
    {
        parent::__construct();
        $this->type = $isBlock ? Element::CodeBlock : Element::InlineCode;
    }
}
