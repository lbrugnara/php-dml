<?php

namespace Dml\Elements;

class Link extends Element
{
    /**
     * @var string
     */
    public $href;

    /**
     * @var string
     */
    public $title;

    public function __construct(string $href, string $title)
    {
        parent::__construct();
        $this->type = Element::Link;
        $this->href = $href;
        $this->title = $title;
    }
}
