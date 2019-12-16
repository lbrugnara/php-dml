<?php

namespace Dml\Parser\Elements;

class Document extends Element
{
    /**
     * @var \Dml\Parser\Elements\Element
     */
    public $head;

    /**
     * @var \Dml\Parser\Elements\Element
     */
    public $body;

    public function __construct()
    {
        parent::__construct();
        $this->type = Element::Document;
        $this->head = new Custom("head");
        $this->body = new Custom("body");
    }
}
