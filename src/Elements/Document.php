<?php

namespace Dml\Elements;

class Document extends Element
{
    /**
     * @var \Dml\Elements\Element
     */
    public $head;

    /**
     * @var \Dml\Elements\Element
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
