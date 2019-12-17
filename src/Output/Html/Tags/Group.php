<?php

namespace Dml\Output\Html\Tags;

class Group extends Tag
{
    public function __construct()
    {
        parent::__construct("", true);
    }

    public function outerXml(): string
    {
        return $this->innerXml();
    }
}