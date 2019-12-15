<?php

namespace Dml;

class ParsingContext
{
    private $markupEnabled;

    public function __construct(ParsingContext $ctx = NULL)
    {
        $this->markupEnabled = $ctx === NULL ? true : $ctx->markupEnabled;
    }

    public function isMarkupEnabled() : bool
    {
        return $this->markupEnabled;
    }

    public function setMarkupStatus(bool $status) : bool
    {
        $oldstate = $this->markupEnabled;
        $this->markupEnabled = $status;
        return $oldstate;
    }
}