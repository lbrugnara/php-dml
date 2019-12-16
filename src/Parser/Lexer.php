<?php

namespace Dml\Parser;

class Lexer {

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
    private const Headers = [ self::Header1, self::Header2, self::Header3, self::Header4 ];

    /**
     * @var array
     */
    private const Lists = [ self::Ulist1, self::Ulist2, self::Ulist3, self::Olist1 ];

    /**
     * @var string
     */
    private $source;

    /**
     * @var int
     */
    private $index;

    /**
     * @var \Dml\Token[]
     */
    private $buffer;

    /**
     * @var \Dml\Token[]
     */
    private $output;

    /**
     * @var \Dml\Token
     */
    private $EOFToken;

    public function __construct(string $source)
    {
        $this->source = str_replace("\r", "", $source);
        $this->index = 0;
        $this->buffer = [];
        $this->output = [];
        $this->EOFToken = new Token(Token::EndOfInput, "EOF", NULL, false);
    }

    /**
     * @return \Dml\Token[]
     */
    public function tokenize() : array
    {
        $tokens = [];

        while (true)
        {
            $token = $this->nextToken();
            if ($token->type == Token::EndOfInput)
                break;
            $tokens[] = $token;
        }

        $this->index = 0;
        $this->buffer = [];
        $this->output = [];

        return $tokens;
    }

    function hasInput() : bool
    {
        return $this->index < strlen($this->source);
    }

    function nextToken() : Token
    {
        if (count($this->buffer) > 0)
        {
            $token = array_shift($this->buffer);
            $this->output[] = $token;
            return $token;
        }

        if (!$this->hasInput())
            return $this->EOFToken;

        $token = $this->nextIsMarkupToken() 
            ? $this->getNextMarkupToken()
            : $this->getNextTextToken();

        $this->output[] = $token;

        return $token;
    }

    public function restoreToken(Token $token) : void
    {
        array_unshift($this->buffer, $token);
    }

    public function peekToken() : Token
    {
        if (count($this->buffer) > 0)
            return $this->buffer[0];

        $token = $this->nextToken();

        array_unshift($this->buffer, $token);

        return $token;
    }

    private function nextIsMarkupToken() : bool
    {
        return $this->nextMarkupToken(new LexerContext(true)) != NULL;
    }

    private function getNextMarkupToken() : Token
    {
        return $this->nextMarkupToken(new LexerContext(false));
    }

    private function nextMarkupToken(LexerContext $ctx) : ?Token
    {
        if (!$this->hasInput())
            return $this->EOFToken;

        return $this->checkNewline($ctx)
                // Block
                ?? $this->checkIndentation($ctx)
                ?? $this->checkThematicBreak($ctx)
                ?? $this->checkListItem($ctx)
                ?? $this->checkTodoListItem($ctx)
                ?? $this->checkNumberedListItem($ctx)
                ?? $this->checkLabeledListItem($ctx)
                ?? $this->checkPreformatted($ctx)
                ?? $this->checkBlockquote($ctx)
                ?? $this->checkHeader($ctx)
                ?? $this->checkCodeBlock($ctx)
                ?? $this->checkDmlCodeBlock($ctx)
                ?? $this->checkCodeBlockLang($ctx)
                ?? $this->checkEscapeBlock($ctx)
                ?? $this->checkReference($ctx)
                // Inline elements
                ?? $this->checkLt($ctx)
                ?? $this->checkEscape($ctx)
                ?? $this->checkColon($ctx)                    
                ?? $this->checkPipe($ctx)
                ?? $this->checkLink($ctx)
                ?? $this->checkImage($ctx)
                ?? $this->checkBold($ctx)
                ?? $this->checkInlineCode($ctx)
                ?? $this->checkItalic($ctx)
                ?? $this->checkUnderlined($ctx)
                ?? $this->checkStrikethrough($ctx);
    }

    private function getNextTextToken() : Token
    {
        $txt = "";

        while ($this->hasInput() && !$this->nextIsMarkupToken())
            $txt .= $this->consumeChar();

        $token = new Token(Token::Text, $txt);

        return $token;
    }

    private function peekChar(int $length = 1) : ?string
    {
        if (!$this->hasInput())
            return NULL;

        if ($this->index + $length >= strlen($this->source))
            return substr($this->source, $this->index, strlen($this->source) - $this->index);

        return substr($this->source, $this->index, $length);
    }

    private function consumeChar(int $length = 1) : ?string
    {
        $tmp = $this->peekChar($length);

        $this->index += $length;

        return $tmp;
    }

    private function isEndOfInput(int $index = 0) :  bool
    {
        return $this->index + $index >= strlen($this->source);
    }

    private function last(array $arr, $default = NULL)
    {
        $key = array_key_last($arr);

        if ($key === NULL)
            return $default;

        return $arr[$key];
    }

    private function isValidBlockStart() : bool
    {
        $key = array_key_last($this->output);

        if ($key === NULL)
            return false;

        $token = $this->last($this->output, NULL);

        if ($token == NULL || $token->type == Token::NewLine 
            || $token->type == Token::DoubleNewLine || $token->type == Token::Blockquote)
            return true;

        $index = count($this->output) - 1;
        while ($index >= 0)
        {
            $token = $this->output[$index--];

            // Skip Indentation, Escape and Empty strings
            if ($token->type == Token::Indentation || $token->type == Token::Escape 
                || ($token->type == Token::Text && strlen(trim($token->value)) == 0))
                continue;
            
            // Break to check for valid start blocks
            break;
        }

        return $token == NULL || $token->type == Token::NewLine 
            || $token->type == Token::DoubleNewLine || $token->type == Token::Blockquote;
    }

    private function checkHeader(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(1);

        if (array_search($lookahead, self::Headers, true) === false)
            return NULL;
        
        if (!$this->isValidBlockStart())
            return NULL;

        if (count($this->output) == 0)
            return NULL;

        // If previous line is just a newline, it is not a header
        if (count($this->output) >= 2 && $this->last($this->output)->type == Token::NewLine 
            && $this->output[count($this->output)-2]->type == Token::NewLine)
            return NULL;

        $tokenval = "";
        $tmp = '';

        while (($tmp = $this->peekChar()) == $lookahead || $tmp === " ")
            $tokenval .= $this->consumeChar();

        if (($tmp === self::NewLine || $this->isEndOfInput()) && strlen($tokenval) >= 4)
        {
            if ($ctx->peek)
                $this->index -= strlen($tokenval);

            return new Token(Token::HeaderStart, $tokenval);
        }

        $this->index -= strlen($tokenval);

        return NULL;
    }

    private function checkThematicBreak(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;

        $lookahead = $this->peekChar(strlen(self::ThematicBreak));

        if ($lookahead === self::ThematicBreak)
            return new Token(Token::ThematicBreak, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::ThematicBreak)));

        return NULL;
    }

    private function checkListItem(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(2);

        if ($this->isValidBlockStart() && strlen($lookahead) == 2 
            && array_search($lookahead[0], self::Lists, true) !== NULL && $lookahead[1] === ' ')
            return new Token(Token::ListItem, $ctx->peek ? $lookahead : $this->consumeChar(2));

        return NULL;
    }

    private function checkTodoListItem(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(4);

        if ($this->isValidBlockStart() && ($lookahead === "[ ] " || $lookahead === "[x] " || $lookahead === "[X] "))
            return new Token(Token::ListItem, $ctx->peek ? $lookahead : $this->consumeChar(4));

        return NULL;
    }

    private function checkNumberedListItem(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;

        $i=1;
        $lookahead = "";
        while (strlen(($lookahead = $this->peekChar($i))) > 0 
            && !$this->isEndOfInput($i) && is_numeric($lookahead))
            $i++;

        $tmp = $this->peekChar(++$i); // The NOT digit that broke the previos while plus the needed space

        if ($tmp == $lookahead) // End of file
            return NULL;

        $tmp_length = strlen($tmp);
        if ($tmp_length >= 2 && (strpos($tmp, ". ", $tmp_length - 2) || strpos($tmp, ") ", $tmp_length - 2)))
        {
            $lookahead = "# ";

            if (!$ctx->peek)
                $this->consumeChar($i);

            return new Token(Token::ListItem, $lookahead, $tmp);
        }

        return NULL;
    }

    private function checkLabeledListItem(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;

        $lookahead = $this->peekChar(3);

        $l_length = $lookahead !== NULL ? strlen($lookahead) : 0;
        if ($lookahead !== NULL && ctype_alpha($lookahead[0]) 
            && $l_length > 2 && (strpos($lookahead, ". ", $l_length - 2) || strpos($lookahead, ") ", $l_length - 2)))
        {
            $originalValue = $lookahead;
            $lookahead = "- ";

            if (!$ctx->peek)
                $this->consumeChar(3);

            return new Token(Token::ListItem, $lookahead, $originalValue);
        }

        return NULL;
    }

    private function checkIndentation(LexerContext $ctx) : ?Token
    {
        $isValidBlockStart = $this->isValidBlockStart();

        // check tab
        $lookahead = $this->peekChar();

        if ($isValidBlockStart && $lookahead === self::Indent)
        {
            if (!$ctx->peek)
                $this->consumeChar();

            return new Token(Token::Indentation, "    ");
        }

        // check at least 4 white spaces
        $lookahead = $this->peekChar(4);

        // If lookahead is NULL or it does not contain at least 4 chars, it is not a pre node
        if ($lookahead == NULL || strlen($lookahead) < 4)
            return NULL;

        // Cannot start a pre node if it is not a valid block start
        if (!$isValidBlockStart)
            return NULL;

        // If all chars are white space and are not new lines, it is an indent token
        if (strpos($lookahead, "\n") !== NULL || strlen(trim($lookahead)) > 0)
            return NULL;

        return new Token(Token::Indentation, $ctx->peek ? $lookahead : $this->consumeChar(4));
    }

    private function checkPreformatted(LexerContext $ctx) : ?Token
    {
        $last = $this->last($this->output);

        if ($last !== NULL && $last->type == Token::Indentation 
            && $this->checkListItem(new LexerContext(true)) == NULL)
            return new Token(Token::Preformatted, "");

        return NULL;
    }

    private function checkNewline(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::DoubleNewLine));

        if ($lookahead === self::DoubleNewLine)
            return new Token(Token::DoubleNewLine, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::DoubleNewLine)));

        $lookahead = $this->peekChar();

        if ($lookahead === self::NewLine)
            return new Token(Token::NewLine, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkLt(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === self::Lt)
            return new Token(Token::Lt, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkBold(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === self::BoldOpen)
            return new Token(Token::BoldOpen, $ctx->peek ? $lookahead : $this->consumeChar());

        if ($lookahead === self::BoldClose)
            return new Token(Token::BoldClose, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkItalic(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::Italic));

        if ($lookahead === self::Italic)
            return new Token(Token::Italic, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Italic)));


        $lookahead = $this->peekChar(strlen(self::Italic2));

        if ($lookahead === self::Italic2)
            return new Token(Token::Italic, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Italic2)));

        return NULL;
    }

    private function checkInlineCode(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === self::InlineCode)
            return new Token(Token::InlineCode, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }        

    private function checkUnderlined(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::Underline));

        if ($lookahead === self::Underline)
            return new Token(Token::Underlined, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Underline)));

        return NULL;
    }

    private function checkPipe(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === self::Pipe)
            return new Token(Token::Pipe, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkReference(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($this->isValidBlockStart() && $lookahead === self::Reference)
            return new Token(Token::Reference, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkColon(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::Colon));

        if ($lookahead === self::Colon)
            return new Token(Token::Colon, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Colon)));

        return NULL;
    }

    private function checkLink(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::LinkOpen));
        $last = $this->last($this->output);
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lookahead === self::LinkOpen && !$lastIsEscape)
            return new Token(Token::LinkStart, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::LinkOpen)));

        if ($lookahead === self::LinkClose && !$lastIsEscape)
            return new Token(Token::LinkEnd, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::LinkClose)));

        return NULL;
    }

    private function checkImage(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::ImgOpen));
        $last = $this->last($this->output);
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lookahead === self::ImgOpen && !$lastIsEscape)
            return new Token(Token::ImageStart, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::ImgOpen)));

        if ($lookahead === self::ImgClose && !$lastIsEscape)
            return new Token(Token::ImageEnd, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::ImgClose)));

        return NULL;
    }

    private function checkStrikethrough(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(strlen(self::Strikethrough));

        if ($lookahead === self::Strikethrough)
            return new Token(Token::Strikethrough, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Strikethrough)));

        return NULL;
    }

    private function checkBlockquote(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;

        $lookahead = $this->peekChar();

        if ($lookahead !== self::Blockquote)
            return NULL;

        $q = 1;
        $tmp = NULL;
        $lookahead = "";
        do
        {
            if ($this->isEndOfInput($q))
                break;

            $tmp = $this->peekChar($q);

            if ($tmp[$q-1] === self::Blockquote[0])
            {
                $q++;
                $lookahead = $tmp;
                continue;
            }

            break;

        } while (true);

        return new Token(Token::Blockquote, $ctx->peek ? $lookahead : $this->consumeChar(max(1, $q-1)));
    }

    private function checkEscapeBlock(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar(2);

        $last = $this->last($this->output);
        if ($lookahead === self::EscapeBlock && ($last === NULL || $last->type != Token::Escape))
            return new Token(Token::EscapeBlock, $ctx->peek ? $lookahead : $this->consumeChar(2));

        return NULL;
    }

    private function checkEscape(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === self::Escape)
            return new Token(Token::Escape, $ctx->peek ? $lookahead : $this->consumeChar());

        return NULL;
    }

    private function checkCodeBlock(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;
            
        $lookahead = $this->peekChar(strlen(self::Codeblock));

        if ($lookahead !== self::Codeblock)
            return NULL;

        
        // Check for more backticks to see if it is a header
        $str = $this->peekChar(strlen(self::Codeblock)+1);

        if (strlen($str) == strlen(self::Codeblock) + 1 && $str[strlen($str) - 1] === self::Codeblock[0])
            return NULL;

        return new Token(Token::CodeBlock,$ctx->peek ? $lookahead : $this->consumeChar(strlen(self::Codeblock)));
    }
    
    private function checkDmlCodeBlock(LexerContext $ctx) : ?Token
    {
        if (!$this->isValidBlockStart())
            return NULL;

        $lookahead = $this->peekChar(strlen(self::DmlCodeblock));

        if ($lookahead !== self::DmlCodeblock)
            return NULL;

        $str = $this->peekChar(strlen(self::DmlCodeblock)+1);

        if (strlen($str) == strlen(self::DmlCodeblock) + 1 && $str[strlen($str) - 1] === self::DmlCodeblock[0])
            return NULL;

        return new Token(Token::CodeBlock, $ctx->peek ? $lookahead : $this->consumeChar(strlen(self::DmlCodeblock)));
    }

    private function checkCodeBlockLang(LexerContext $ctx) : ?Token
    {
        $lookahead = $this->peekChar();
        $last = $this->last($this->output);

        if ($last === NULL || $last->type !== Token::CodeBlock || $lookahead == NULL || $lookahead == "\n")
            return NULL;

        $lookahead = "";
        $q = 1;
        $tmp = NULL;            
        while (($tmp = $this->peekChar($q)) != $lookahead && strlen($tmp) >= 1 && $tmp[strlen($tmp)-1] != "\n")
        {
            $lookahead = $tmp;
            $q++;
        }

        if (!$ctx->peek)
            $this->consumeChar($q-1);

        return new Token(Token::CodeBlockLang, $lookahead);
    }
}
