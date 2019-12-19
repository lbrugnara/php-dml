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
    const Header1 = 14;
    const Header2 = 15;
    const Header3 = 16;
    const Header4 = 17;
    const LabeledListItem = 18;
    const NumberedListItem = 19;
    const TodoListItem = 20;
    const UnorderedListItem = 21;
    const Pipe = 22;
    const Reference = 23;
    const Colon = 24;
    const LinkStart = 25;
    const LinkEnd = 26;
    const ImageStart = 27;
    const ImageEnd = 28;
    const Escape = 29;
    const EscapeBlock = 30;
    const ThematicBreak = 31;
    const BlockquoteEndMarker = 32;
    const Lt = 33;

    /**
     * @var int
     */
    public $type;

    /**
     * Token start position
     *
     * @var int
     */
    public $position;

    /**
     * Token length
     *
     * @var int
     */
    public $length;

    public function __construct(int $type, int $position, int $length)
    {
        $this->type = $type;
        $this->position = $position;
        $this->length = $length;
    }
}
