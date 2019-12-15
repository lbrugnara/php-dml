<?php

namespace Dml\Elements;

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
    public $alttitle;

    public function __construct(string $title, string $source, string $alttitle)
    {
        parent::__construct();
        $this->type = Element::Image;
        $this->title = $title;
        $this->source = $source;
        $this->alttitle = $alttitle;
    }
}
