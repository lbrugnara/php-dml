<?php

namespace Dml\Parser\Elements;

class Header extends Element
{
    const H1 = 'h1';
    const H2 = 'h2';
    const H3 = 'h3';
    const H4 = 'h4';

    /**
     * @var string
     */
    public $header;

    public function __construct(string $header)
    {
        parent::__construct();
        $this->type = Element::Header;
        $this->header = $header;
    }
}
