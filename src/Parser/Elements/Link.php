<?php

namespace Dml\Parser\Elements;

class Link extends Element
{
    /**
     * @var string
     */
    public $href;

    /**
     * @var null|string
     */
    public $title;

    public function __construct(string $href, ?string $title)
    {
        parent::__construct();
        $this->type = Element::Link;
        $this->href = $href;
        $this->title = $title;
    }
}
