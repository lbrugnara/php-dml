<?php

namespace Dml\Parser;

class Lexeme {

    // Markup tags
    /**
     * @var string
     */
    const Header1 = "=";

    /**
     * @var string
     */
    const Header2 = "~";

    /**
     * @var string
     */
    const Header3 = "-";

    /**
     * @var string
     */
    const Header4 = "`";

    /**
     * @var string
     */
    const Ulist1 = "-";        

    /**
     * @var string
     */
    const Ulist2 = "+";

    /**
     * @var string
     */
    const Ulist3 = "*";

    /**
     * @var string
     */
    const Olist1 = "#";        

    /**
     * @var string
     */
    const ThematicBreak = "- - -";

    /**
     * @var string
     */
    const Blockquote = ">";

    /**
     * @var string
     */
    const Codeblock = "```";

    /**
     * @var string
     */
    const DmlCodeblock = "!```";        

    /**
     * @var string
     */
    const Pipe = "|";

    /**
     * @var string
     */
    const Reference = "|";

    /**
     * @var string
     */
    const Colon = ":";

    /**
     * @var string
     */
    const InlineCode = "`";

    /**
     * @var string
     */
    const EscapeBlock = "``";

    /**
     * @var string
     */
    const Escape = "\\";

    /**
     * @var string
     */
    const Indent = "\t";

    /**
     * @var string
     */
    const NewLine = "\n";

    /**
     * @var string
     */
    const DoubleNewLine = "\n\n";

    /**
     * @var string
     */
    const Italic = "//";

    /**
     * @var string
     */
    const Italic2 = "´";

    /**
     * @var string
     */
    const Underline = "__";

    /**
     * @var string
     */
    const Strikethrough = "~~";

    /**
     * @var string
     */
    const BoldOpen = "[";

    /**
     * @var string
     */
    const BoldClose = "]";

    /**
     * @var string
     */
    const LinkOpen = "[[";

    /**
     * @var string
     */
    const LinkClose = "]]";

    /**
     * @var string
     */
    const ImgOpen = "[{";

    /**
     * @var string
     */
        const ImgClose = "}]";

    /**
     * @var string
     */
    const Lt = "<";

    /**
     * @var array
     */
    public const Headers = [ self::Header1, self::Header2, self::Header3, self::Header4 ];

    /**
     * @var array
     */
    public const Lists = [ self::Ulist1, self::Ulist2, self::Ulist3, self::Olist1 ];

    public const Lengths = [
        self::Header1           => 1,
        self::Header2           => 1,
        self::Header3           => 1,
        self::Header4           => 1,
        self::Ulist1            => 1,        
        self::Ulist2            => 1,
        self::Ulist3            => 1,
        self::Olist1            => 1,        
        self::ThematicBreak     => 5,
        self::Blockquote        => 1,
        self::Codeblock         => 3,
        self::DmlCodeblock      => 4,
        self::Pipe              => 1,
        self::Reference         => 1,
        self::Colon             => 1,
        self::InlineCode        => 1,
        self::EscapeBlock       => 2,
        self::Escape            => 1,
        self::Indent            => 1,
        self::NewLine           => 1,
        self::DoubleNewLine     => 2,
        self::Italic            => 2,
        self::Italic2           => 1,
        self::Underline         => 2,
        self::Strikethrough     => 2,
        self::BoldOpen          => 1,
        self::BoldClose         => 1,
        self::LinkOpen          => 2,
        self::LinkClose         => 2,
        self::ImgOpen           => 2,
        self::ImgClose          => 2,
        self::Lt                => 1,
    ];
}
