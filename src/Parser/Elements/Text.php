<?php

namespace Dml\Parser\Elements;

class Text extends Element
{
    /**
     * @var string
     */
    public $content;

    public function __construct(string $content)
    {
        parent::__construct();
        $this->type = Element::Text;
        $this->content = $content;
    }
}
