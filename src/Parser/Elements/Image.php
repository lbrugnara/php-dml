<?php

namespace Dml\Parser\Elements;

class Image extends Element
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $altTitle;

    public function __construct(string $title, string $source, string $altTitle)
    {
        parent::__construct();
        $this->type = Element::Image;
        $this->title = $title;
        $this->source = $source;
        $this->altTitle = $altTitle;
    }
}
