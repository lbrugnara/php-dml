<?php

namespace Dml\Parser;

class Token {
    const Unknown = -2;
    const EndOfInput = -1;
    const NewLine = 0;
    const DoubleNewLine = 1;
    const Indentation = 2;
    const BoldOpen = 3;
    const BoldClose = 4;
    const Italic = 5;
    const Underlined = 6;
    const InlineCode = 7;
    const CodeBlock = 8;
    const CodeBlockLang = 9;
    const Preformatted = 10;
    const Strikethrough = 11;
    const Text = 12;
    const Blockquote = 13;
    const HeaderStart = 14;
    const HeaderEnd = 15;
    const ListItem = 16;
    const Pipe = 17;
    const Reference = 18;
    const Colon = 19;
    const LinkStart = 20;
    const LinkEnd = 21;
    const ImageStart = 22;
    const ImageEnd = 23;
    const Escape = 24;
    const EscapeBlock = 25;
    const ThematicBreak = 26;
    const BlockquoteEndMarker = 27;
    const Lt = 28;

    /**
     * @var int
     */
    public $type;

    /**
     * @var string
     */
    public $value;

    /**
     * @var string
     */
    public $originalValue;

    /**
     * @var bool
     */
    public $isRepresentable;

    public function __construct(int $type, string $value, ?string $originalValue = NULL, bool $isRepresentable = true)
    {
        $this->type = $type;
        $this->value = $value;
        $this->originalValue = $originalValue;
        $this->isRepresentable = $isRepresentable;
    }
}
