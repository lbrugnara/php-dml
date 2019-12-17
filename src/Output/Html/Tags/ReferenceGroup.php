<?php

namespace Dml\Output\Html\Tags;

class ReferenceGroup extends Tag
{
    public function __construct()
    {
        parent::__construct("", true);
    }

    public function innerXml(): string
    {
        $output = "";
        
        foreach ($this->children as $child)
            $output .= trim($child->outerXml());

        return $output;
    }
    
    public function outerXml(): string
    {
        return $this->innerXml();
    }
}