<?php

namespace Dml\Output\Html\Tags;

class Link extends Tag
{
    public function __construct(string $href, string $title)
    {
        parent::__construct("a", true);
        $this->attributes['href'] = $href;
        $this->attributes['title'] = $title;
    }

    function innerText(): string
    {
        return trim(parent::innerText());
    }

    function innerXml(): string
    {
        return trim(parent::innerXml());
    }
}