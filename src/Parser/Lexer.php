<?php

namespace Dml\Parser;

class Lexer
{
    /**
     * @var string
     */
    private $source;

    /**
     * Source length
     *
     * @var int
     */
    private $sourceLength;

    /**
     * @var int
     */
    private $index;

    /**
     * @var \Dml\Token[]
     */
    private $output;

    /**
     * Output buffer length
     *
     * @var int
     */
    private $outputLength;

    /**
     * @var \Dml\Token
     */
    private $EOFToken;

    /**
     * Lexical context
     *
     * @var \Dml\Parser\LexerContext
     */
    private $context;

    private $flags;

    const BlockStart = 0x1;
    
    private const TextStoppers = [
        1 => [
            Lexeme::Header1         => 0,
            Lexeme::Header2         => 0,
            Lexeme::Header3         => 0,
            Lexeme::Header4         => 0,
            Lexeme::UList1          => 0,
            Lexeme::UList2          => 0,
            Lexeme::UList3          => 0,
            Lexeme::OList1          => 0,
            Lexeme::Blockquote      => 0,
            Lexeme::Pipe            => 0,
            Lexeme::Reference       => 0,
            Lexeme::Colon           => 0,
            Lexeme::InlineCode      => 0,
            Lexeme::Escape          => 0,
            Lexeme::Indent          => 0,
            Lexeme::NewLine         => 0,
            Lexeme::BoldOpen        => 0,
            Lexeme::BoldClose       => 0,
            Lexeme::Lt              => 0,
        ],
        
        2 => [
            Lexeme::EscapeBlock     => 0,
            Lexeme::DoubleNewLine   => 0,
            Lexeme::Italic          => 0,
            Lexeme::Underline       => 0,
            Lexeme::Strikethrough   => 0,
            Lexeme::LinkOpen        => 0,
            Lexeme::LinkClose       => 0,
            Lexeme::ImgOpen         => 0,
            Lexeme::ImgClose        => 0,
            Lexeme::Italic2         => 0,
        ],
        
        3 => [
            Lexeme::Codeblock       => 0
        ],
        
        4 => [
            Lexeme::DmlCodeblock    => 0
        ],
        
        5 => [
            Lexeme::ThematicBreak   => 0
        ]
    ];

    public function __construct(string $source)
    {
        $this->source = str_replace("\r", "", $source);
        $this->sourceLength = strlen($this->source);
        $this->index = 0;
        $this->output = [];
        $this->outputLength = 0;
        $this->EOFToken = new Token(Token::EndOfInput, "EOF", NULL, false);
        $this->context = new LexerContext();
        $this->flags = self::BlockStart;
    }

    /**
     * @return \Dml\Parser\Token[]
     */
    public function tokenize() : array
    {
        $tokens = [];

        while (true)
        {
            $token = $this->nextToken();
            if ($token->type === Token::EndOfInput)
                break;
            $tokens[] = $token;
        }

        $this->index = 0;
        $this->output = [];
        $this->outputLength = 0;

        return $tokens;
    }

    private function peekChar(int $length = 1) : ?string
    {
        if ($this->index >= $this->sourceLength)
            return NULL;

        if ($this->index + $length >= $this->sourceLength)
            $length = $this->sourceLength - $this->index;
            
        if ($length == 1)
            return $this->source[$this->index];

        return substr($this->source, $this->index, $length);
    }

    private function consumeChar(int $length = 1) : ?string
    {
        if ($this->index >= $this->sourceLength)
            return NULL;

        if ($this->index + $length >= $this->sourceLength)
            $length = $this->sourceLength - $this->index;

        if ($length == 1)
            $char = $this->source[$this->index];
        else
            $char = substr($this->source, $this->index, $length);

        $this->index += $length;

        return $char;
    }

    function hasInput() : bool
    {
        return $this->index < $this->sourceLength || $this->peek_index > 0;
    }

    private function isEndOfInput(int $offset = 0) :  bool
    {
        return $this->index + $offset >= $this->sourceLength;
    }        

    private function lastToken() : ?Token
    {
        if ($this->peek_index > 0)
            return $this->peek_buffer[$this->peek_index - 1]['t'];

        if ($this->outputLength <= 0)
            return NULL;

        return $this->output[$this->outputLength - 1];
    }

    private function setFlags(Token $token) : void
    {
        if ($token->type === Token::NewLine || $token->type === Token::DoubleNewLine || $token->type === Token::Blockquote)
        {
            // Set the BlockStart on new lines
            $this->flags |= self::BlockStart;
        }
        else if ($token->type !== Token::Indentation && $token->type !== Token::Escape && ($token->type !== Token::Text || isset($token->value[0])))
        {
            // Skip Indentation, Escape and Empty strings to clear the BlockStart
            $this->flags &= ~self::BlockStart;
        }
    }

    function nextToken() : Token
    {
        if ($this->peek_index > 0)
        {
            $buffered = \array_shift($this->peek_buffer);
            $this->peek_index--;

            $token = $buffered['t'];
            $this->output[] = $token;
            $this->outputLength++;
            $this->index = $buffered['i'];
            $this->flags = $buffered['f'];

            return $token;
        }

        if ($this->index >= $this->sourceLength)
            return $this->EOFToken;

        $token = $this->getNextMarkupToken() ?? $this->getNextTextToken();

        $this->output[] = $token;
        $this->outputLength++;
        $this->setFlags($token);

        return $token;
    }

    private $peek_buffer = [];
    private $peek_index = 0;

    public function peekToken(int $offset = 0) : Token
    {
        if (isset($this->peek_buffer[$offset]))
        {
            $buffered = $this->peek_buffer[$offset];
            $token = $buffered['t'];
            $this->flags = $buffered['f'];
            return $token;
        }

        if ($this->index >= $this->sourceLength)
            return $this->EOFToken;
            
        $prev_flags = $this->flags;
        $prev_index = $this->index;

        if ($this->peek_index > 0)
        {
            $this->index = $this->peek_buffer[$this->peek_index - 1]['i'];
            $offset -= $this->peek_index;
        }

        $peeks = 0;
        do
        {
            $token = $this->getNextMarkupToken() ?? $this->getNextTextToken();
            $this->setFlags($token);
            $this->peek_buffer[$this->peek_index++] = [ 't' => $token, 'i' => $this->index, 'f' => $this->flags ];
        } while (++$peeks < $offset);

        $this->index = $prev_index;
        $this->flags = $prev_flags;

        return $token;
    }

    private function getNextMarkupToken() : ?Token
    {
        if (!$this->hasInput())
            return $this->EOFToken;

        $c = $this->peekChar();
        switch ($c)
        {
            case '=':
                return $this->checkHeader();            // = (multiple)
            
            case '~':
                return $this->checkHeader()             // ~ (multiple)
                        ?? $this->checkStrikethrough(); // ~~

            case '-':
                return $this->checkHeader()             // - (multiple)
                        ?? $this->checkThematicBreak()  // - - -
                        ?? $this->checkListItem();      // -      
            
            case '`':
                return $this->checkHeader()             // ` (multiple)
                        ?? $this->checkCodeBlock()      // ```
                        ?? $this->checkEscapeBlock()    // ``
                        ?? $this->checkInlineCode();    // `

            case '+':
                return $this->checkListItem();          // +

            case '*':
                return $this->checkListItem();          // *

            case '#':
                return $this->checkListItem();          // #

            case '>':
                return $this->checkBlockquote();        // > (multiple)

            case '!':
                return $this->checkDmlCodeBlock();      // !```

            case '|':
                return $this->checkReference()          // |
                        ?? $this->checkPipe();          // |

            case ':':
                return $this->checkColon();             // :

            case "\n":
                return $this->checkNewline();           // \n and \n\n

            case "\t":
            case " ":
                return $this->checkIndentation();       // \t

            case "\\":
                return  $this->checkEscape();           // \\

            case '/':
                return $this->checkItalic();            // //

            case '_':
                return $this->checkUnderlined();        // __

            case '[':
            case ']':
                return $this->checkLink()               // [[
                        ?? $this->checkImage()          // [{
                        ?? $this->checkTodoListItem()   // [ ] , [x] , [X] 
                        ?? $this->checkBold();          // [

            case '<':
                return $this->checkLt();                // <

            default:
                if (\is_numeric($c))
                    return $this->checkNumberedListItem();  // 1. , 1)

                if ($this->peekChar(2) == '´')
                    return $this->checkItalic();            // ´

                return $this->checkLabeledListItem()        // a. , b. , A. , B. 
                        ?? $this->checkPreformatted()       // \t or \s{4}
                        ?? $this->checkCodeBlockLang();     // (string following a ```)
        }
    }

    private function getNextTextToken() : ?Token
    {
        $txt = "";

        $c = NULL;
        $pc = NULL;
        $ppc = NULL;
        $pppc = NULL;
        $ppppc = NULL;

        while ($this->hasInput())
        {
            // Update the previous occurrences
            if ($pppc !== NULL)
                $ppppc = $pppc;
            if ($ppc !== NULL)
                $pppc = $ppc;
            if ($pc !== NULL)
                $ppc = $pc;
            if ($c !== NULL)
                $pc = $c;

            $c = $this->peekChar();
            
            // We need to check if the next character (or a string compound with
            // previous consumed chars) is a possible markup token, for that
            // we use the "stops" array with the markup tokens.
            // For every string length, we need to "go back" to check if there is a 
            // "token match" that could result in a markup token being processed
            $offset_back = -1;
            if (isset(self::TextStoppers[5][ $ppppc . $pppc . $ppc . $pc . $c ]))
            {
                // At this point we need to go back 4 times to know
                // if the next token is a markup element
                $offset_back = 4;
            }
            else if (isset(self::TextStoppers[4][ $pppc . $ppc . $pc . $c ]))
            {
                // At this point we need to go back 3 times to know
                // if the next token is a markup element
                $offset_back = 3;
            }
            else if (isset(self::TextStoppers[3][ $ppc . $pc . $c ]))
            {
                // At this point we need to go back 2 times to know
                // if the next token is a markup element
                $offset_back = 2;
            }
            else if (isset(self::TextStoppers[2][ $pc . $c ]))
            {
                // At this point we need to go back 2 times to know
                // if the next token is a markup element
                $offset_back = 1;
            }
            else if (isset(self::TextStoppers[1][$c]))
            {
                // At this point we don't need to move the index pointer
                // as we didn't consume the character being pointed, but we 
                // do this to trigger the "check markup token" path
                $offset_back = 0;
            }

            if ($offset_back >= 0)
            {
                $this->index -= $offset_back;
                $tmp_token = $this->getNextMarkupToken();
                if ($tmp_token !== NULL)
                {
                    // The token being not null means we need to move the pointer
                    // back to let the next call get it again, but we also need to
                    // remove the chars that belong to the next token that have been
                    // already consumed ($pc, $ppc, etc) and are present in the $txt variable
                    $this->index -= strlen($tmp_token->originalValue ?? $tmp_token->value);
                    $txt = \substr($txt, 0, strlen($txt) - $offset_back);
                    break;
                }
                // If the token is null, it means the character is a "stopper", but in the current
                // context, it is NOT a markup token (like a list token "-" that is not preceded by a 
                // valid starting block)
            }

            $txt .= $this->consumeChar();            
        }

        if (strlen($txt) === 0)
            return NULL;

        $token = new Token(Token::Text, $txt);

        return $token;
    }

    private function checkHeader() : ?Token
    {
        $lookahead = $this->peekChar(1);

        if (!isset(Lexeme::Headers[$lookahead[0]]))
            return NULL;
        
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        if ($this->outputLength == 0)
            return NULL;

        // If previous line is just a newline, it is not a header
        if ($this->outputLength >= 2 && $this->lastToken()->type === Token::NewLine 
            && $this->output[$this->outputLength-2]->type === Token::NewLine)
            return NULL;

        $tokenval = "";
        $tmp = '';

        while (($tmp = $this->peekChar()) == $lookahead || $tmp === " ")
            $tokenval .= $this->consumeChar();

        if (($tmp === Lexeme::NewLine || $this->isEndOfInput()) && strlen($tokenval) >= 4)
            return new Token(Token::HeaderStart, $tokenval);

        $this->index -= strlen($tokenval);

        return NULL;
    }

    private function checkThematicBreak() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::ThematicBreak]);

        if ($lookahead === Lexeme::ThematicBreak)
            return new Token(Token::ThematicBreak, $this->consumeChar(Lexeme::Lengths[Lexeme::ThematicBreak]));

        return NULL;
    }

    private function checkListItem() : ?Token
    {
        $lookahead = $this->peekChar(2);

        if (($this->flags & self::BlockStart) && strlen($lookahead) == 2 
            && isset(Lexeme::Lists[$lookahead[0]]) && $lookahead[1] === ' ')
            return new Token(Token::ListItem, $this->consumeChar(2));

        return NULL;
    }

    private function checkTodoListItem() : ?Token
    {
        $lookahead = $this->peekChar(4);

        if (($this->flags & self::BlockStart) && ($lookahead === "[ ] " || $lookahead === "[x] " || $lookahead === "[X] "))
            return new Token(Token::ListItem, $this->consumeChar(4));

        return NULL;
    }

    private function checkNumberedListItem() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        $i=1;
        $lookahead = "";
        while (strlen(($lookahead = $this->peekChar($i))) > 0 && !$this->isEndOfInput($i) && is_numeric($lookahead))
            $i++;

        $tmp = $this->peekChar(++$i); // The NOT digit that broke the previos while plus the needed space

        if ($tmp == $lookahead) // End of file
            return NULL;

        $tmp = $lookahead;
        $tmp_length = strlen($tmp);
        if ($tmp_length >= 2 && (strpos($tmp, ". ", $tmp_length - 3) || strpos($tmp, ") ", $tmp_length - 3)))
        {
            $lookahead = "# ";

            $this->consumeChar($i - 1);

            return new Token(Token::ListItem, $lookahead, $tmp);
        }

        return NULL;
    }

    private function checkLabeledListItem() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        $lookahead = $this->peekChar(3);

        $l_length = $lookahead !== NULL ? strlen($lookahead) : 0;
        if ($lookahead !== NULL && ctype_alpha($lookahead[0]) && $l_length > 2 && (strpos($lookahead, ". ", $l_length - 2) || strpos($lookahead, ") ", $l_length - 2)))
        {
            $originalValue = $lookahead;
            $lookahead = "- ";

            $this->consumeChar(3);

            return new Token(Token::ListItem, $lookahead, $originalValue);
        }

        return NULL;
    }

    private function checkIndentation() : ?Token
    {
        $isValidBlockStart = ($this->flags & self::BlockStart);

        // check tab
        $lookahead = $this->peekChar();

        if ($isValidBlockStart && $lookahead === Lexeme::Indent)
        {
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
        if (isset(trim($lookahead)[0]))
            return NULL;

        return new Token(Token::Indentation, $this->consumeChar(4));
    }

    private function checkPreformatted() : ?Token
    {
        $last = $this->lastToken();

        if ($last !== NULL && $last->type === Token::Indentation 
            && $this->checkListItem() == NULL)
            return new Token(Token::Preformatted, "");

        return NULL;
    }

    private function checkNewline() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::DoubleNewLine]);

        if ($lookahead === Lexeme::DoubleNewLine)
            return new Token(Token::DoubleNewLine, $this->consumeChar(Lexeme::Lengths[Lexeme::DoubleNewLine]));

        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::NewLine)
            return new Token(Token::NewLine, $this->consumeChar());

        return NULL;
    }

    private function checkLt() : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::Lt)
            return new Token(Token::Lt, $this->consumeChar());

        return NULL;
    }

    private function checkBold() : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::BoldOpen)
            return new Token(Token::BoldOpen, $this->consumeChar());

        if ($lookahead === Lexeme::BoldClose)
            return new Token(Token::BoldClose, $this->consumeChar());

        return NULL;
    }

    private function checkItalic() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Italic]);

        if ($lookahead === Lexeme::Italic)
            return new Token(Token::Italic, $this->consumeChar(Lexeme::Lengths[Lexeme::Italic]));


        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Italic2]);

        if ($lookahead === Lexeme::Italic2)
            return new Token(Token::Italic, $this->consumeChar(Lexeme::Lengths[Lexeme::Italic2]));

        return NULL;
    }

    private function checkInlineCode() : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::InlineCode)
            return new Token(Token::InlineCode, $this->consumeChar());

        return NULL;
    }        

    private function checkUnderlined() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Underline]);

        if ($lookahead === Lexeme::Underline)
            return new Token(Token::Underlined, $this->consumeChar(Lexeme::Lengths[Lexeme::Underline]));

        return NULL;
    }

    private function checkPipe() : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::Pipe)
            return new Token(Token::Pipe, $this->consumeChar());

        return NULL;
    }

    private function checkReference() : ?Token
    {
        $lookahead = $this->peekChar();

        if (($this->flags & self::BlockStart) && $lookahead === Lexeme::Reference)
            return new Token(Token::Reference, $this->consumeChar());

        return NULL;
    }

    private function checkColon() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Colon]);

        if ($lookahead === Lexeme::Colon)
            return new Token(Token::Colon, $this->consumeChar(Lexeme::Lengths[Lexeme::Colon]));

        return NULL;
    }

    private function checkLink() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::LinkOpen]);
        $last = $this->lastToken();
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lookahead === Lexeme::LinkOpen && !$lastIsEscape)
            return new Token(Token::LinkStart, $this->consumeChar(Lexeme::Lengths[Lexeme::LinkOpen]));

        if ($lookahead === Lexeme::LinkClose && !$lastIsEscape)
            return new Token(Token::LinkEnd, $this->consumeChar(Lexeme::Lengths[Lexeme::LinkClose]));

        return NULL;
    }

    private function checkImage() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::ImgOpen]);
        $last = $this->lastToken();
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lookahead === Lexeme::ImgOpen && !$lastIsEscape)
            return new Token(Token::ImageStart, $this->consumeChar(Lexeme::Lengths[Lexeme::ImgOpen]));

        if ($lookahead === Lexeme::ImgClose && !$lastIsEscape)
            return new Token(Token::ImageEnd, $this->consumeChar(Lexeme::Lengths[Lexeme::ImgClose]));

        return NULL;
    }

    private function checkStrikethrough() : ?Token
    {
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Strikethrough]);

        if ($lookahead === Lexeme::Strikethrough)
            return new Token(Token::Strikethrough, $this->consumeChar(Lexeme::Lengths[Lexeme::Strikethrough]));

        return NULL;
    }

    private function checkBlockquote() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        $lookahead = $this->peekChar();

        if ($lookahead !== Lexeme::Blockquote)
            return NULL;

        $q = 1;
        $tmp = NULL;
        $lookahead = "";
        do
        {
            if ($this->isEndOfInput($q))
                break;

            $tmp = $this->peekChar($q);

            if ($tmp[$q-1] === Lexeme::Blockquote[0])
            {
                $q++;
                $lookahead = $tmp;
                continue;
            }

            break;

        } while (true);

        return new Token(Token::Blockquote, $this->consumeChar(max(1, $q-1)));
    }

    private function checkEscapeBlock() : ?Token
    {
        $lookahead = $this->peekChar(2);

        $last = $this->lastToken();
        if ($lookahead === Lexeme::EscapeBlock && ($last === NULL || $last->type != Token::Escape))
            return new Token(Token::EscapeBlock, $this->consumeChar(2));

        return NULL;
    }

    private function checkEscape() : ?Token
    {
        $lookahead = $this->peekChar();

        if ($lookahead === Lexeme::Escape)
            return new Token(Token::Escape, $this->consumeChar());

        return NULL;
    }

    private function checkCodeBlock() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;
            
        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::Codeblock]);

        if ($lookahead !== Lexeme::Codeblock)
            return NULL;

        
        // Check for more backticks to see if it is a header
        $str = $this->peekChar(Lexeme::Lengths[Lexeme::Codeblock]+1);
        $strLength = strlen($str);
        if ($strLength == Lexeme::Lengths[Lexeme::Codeblock] + 1 && $str[$strLength - 1] === Lexeme::Codeblock[0])
            return NULL;

        return new Token(Token::CodeBlock,$this->consumeChar(Lexeme::Lengths[Lexeme::Codeblock]));
    }
    
    private function checkDmlCodeBlock() : ?Token
    {
        if (~($this->flags & self::BlockStart) == 0)
            return NULL;

        $lookahead = $this->peekChar(Lexeme::Lengths[Lexeme::DmlCodeblock]);

        if ($lookahead !== Lexeme::DmlCodeblock)
            return NULL;

        $str = $this->peekChar(Lexeme::Lengths[Lexeme::DmlCodeblock]+1);
        $strLength = strlen($str);
        if ($strLength == Lexeme::Lengths[Lexeme::DmlCodeblock] + 1 && $str[$strLength - 1] === Lexeme::DmlCodeblock[0])
            return NULL;

        return new Token(Token::CodeBlock, $this->consumeChar(Lexeme::Lengths[Lexeme::DmlCodeblock]));
    }

    private function checkCodeBlockLang() : ?Token
    {
        $lookahead = $this->peekChar();
        $last = $this->lastToken();

        if ($last === NULL || $last->type !== Token::CodeBlock || $lookahead == NULL || $lookahead == "\n")
            return NULL;

        $lookahead = "";
        $q = 1;
        $tmp = NULL;
        while (($tmp = $this->peekChar($q)) != $lookahead && isset($tmp[$q-1]) && $tmp[$q-1] != "\n")
        {
            $lookahead = $tmp;
            $q++;
        }

        $this->consumeChar($q-1);

        return new Token(Token::CodeBlockLang, $lookahead);
    }
}
