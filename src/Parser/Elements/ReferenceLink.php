<?php

namespace Dml\Parser\Elements;

class ReferenceLink extends Element
{
    public $reference;
    public $title;

    public function __construct(string $reference, ?string $title)
    {
        parent::__construct();

        $this->type = Element::ReferenceLink;
        $this->reference = $reference;
        $this->title = $title;
    }
}
