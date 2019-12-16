<?php

namespace Dml\Parser\Elements;

class ReferenceLink extends Element
{
    public function __construct(string $reference, ?string $title)
    {
        parent::__construct();

        $this->type = Element::ReferenceLink;

        $link = new Link("#" . $reference, $reference);
        $link->children[] = new Text(
            $title !== NULL && strlen($title) != 0 
            ? $title
            : $reference
        );

        $this->children[] = $link;
    }
}
