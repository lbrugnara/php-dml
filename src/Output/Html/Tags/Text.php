<?php

namespace Dml\Output\Html\Tags;

class Text extends Tag
{
    /**
     * Node content
     *
     * @var string
     */
    private $content;

    public function __construct(string $content)
    {
        parent::__construct("", true);
        $this->content = $content;
    }

    function innerText(): string
    {
        return $this->content;
    }

    function innerXml(): string
    {
        return $this->content;
    }

    function outerXml(): string
    {
        return $this->content;
    }
}