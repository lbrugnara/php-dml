<?php

namespace Dml\Output\Html\Tags;

class Image extends Tag
{
    public function __construct(string $title, string $source, string $altTitle)
    {
        parent::__construct("img", false);
        $this->attributes['title'] = $title;
        $this->attributes['src'] = $source;
        $this->attributes['alt'] = $altTitle;
    }
}