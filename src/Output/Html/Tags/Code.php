<?php

namespace Dml\Output\Html\Tags;

class Code extends Tag
{
    /**
     * True if this code is a block element
     *
     * @var bool
     */
    private $isBlock;

    public function __construct(bool $isBlock)
    {
        parent::__construct("code", true);
        $this->isBlock = $isBlock;

        if ($this->isBlock)
        {
            $this->attributes["style"] = "display: block; white-space: pre-wrap";
        }
    }
}