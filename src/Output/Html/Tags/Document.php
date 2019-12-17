<?php

namespace Dml\Output\Html\Tags;

class Document extends Tag
{
    /**
     * Head tag
     *
     * @var \Dml\Output\Html\Tags\Tag
     */
    public $head;

    /**
     * Body tag
     *
     * @var \Dml\Output\Html\Tags\Tag
     */
    public $body;

    public function __construct()
    {
        parent::__construct("html", true);
    }
}