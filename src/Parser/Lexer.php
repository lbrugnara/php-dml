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
     * @var \Dml\Parser\Token[]
     */
    private $output;

    /**
     * Output buffer length
     *
     * @var int
     */
    private $outputLength;

    /**
     * @var \Dml\Parser\Token
     */
    private $EOFToken;

    /**
     * Lexical context
     *
     * @var \Dml\Parser\LexerContext
     */
    private $context;

    private $flags;

    const StartOfInput  = 0x01;
    const BlockStart    = 0x02;
    const NewLine       = 0x04;
    const DoubleNewLine = 0x08;
    const Blockquote    = 0x10;
    const EndOfInput    = 0x80;
    
    private const TextStoppers = [
        1 => [
            Lexeme::Pipe            => 0,
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
    ];

    public function __construct(string $source)
    {
        $this->source = str_replace("\r", "", $source);
        $this->sourceLength = strlen($this->source);
        $this->index = 0;
        $this->output = [];
        $this->outputLength = 0;
        $this->EOFToken = new Token(Token::EndOfInput, $this->sourceLength, 0);
        $this->context = new LexerContext();
        $this->flags = self::StartOfInput | self::BlockStart;
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

    private function accept(string $lexeme, int $length) : bool
    {
        if ($this->index + $length > $this->sourceLength)
            return false;

        for ($s = 0, $e = $length-1; $s <= $e; $s++, $e--)
            if ($lexeme[$s] != $this->source[$this->index + $s]
                || $lexeme[$e] != $this->source[$this->index + $e])
                return false;

        return true;
    }

    private function peekCharAt(int $offset = 0) : ?string
    {
        if ($this->index + $offset >= $this->sourceLength)
            return NULL;
            
        return $this->source[$this->index + $offset];
    }

    private function emit(int $type, int $length = 1) : Token
    {
        $token = new Token($type, $this->index, $length);
        $this->index += $length;
        return $token;
    }

    function hasInput() : bool
    {
        return $this->index < $this->sourceLength || $this->peek_index > 0;
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
        if ($token->type === Token::NewLine)
        {
            $this->flags |= self::BlockStart | self::NewLine;
            $this->flags &= ~self::Blockquote;
        }
        else if ($token->type == Token::DoubleNewLine)
        {
            $this->flags |= self::BlockStart | self::DoubleNewLine;
            $this->flags &= ~self::Blockquote;
        }
        else
        {
            $this->flags &= ~self::NewLine & ~self::DoubleNewLine;

            if ($token->type === Token::Blockquote)
            {
                // Set the BlockStart on new lines
                $this->flags |= self::BlockStart | self::Blockquote;
            }
            else if ($token->type !== Token::Indentation && $token->type !== Token::Escape && ($token->type != Token::Text || $token->length > 0))
            {
                // Skip Indentation, Escape and Empty strings to clear the BlockStart
                $this->flags &= ~self::BlockStart;
            }
        }

        if ($this->index == 0)
            $this->flags |= self::StartOfInput;
        if ($this->index >= $this->sourceLength)
            $this->flags |= self::EndOfInput;
        else
            $this->flags &= ~self::StartOfInput;
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

    public function peekToken(int $offset = 0) : ?Token
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

        $token = NULL;
        $peeks = 0;
        do
        {
            $token = $this->getNextMarkupToken() ?? $this->getNextTextToken();
            if ($token != NULL)
            {
                $this->setFlags($token);
                $this->peek_buffer[$this->peek_index++] = [ 't' => $token, 'i' => $this->index, 'f' => $this->flags ];
            }
        } while (++$peeks < $offset && $token != NULL);

        $this->index = $prev_index;
        $this->flags = $prev_flags;

        return $token;
    }

    private function getNextMarkupToken() : ?Token
    {
        if (!$this->hasInput())
            return $this->EOFToken;

        if (($this->flags & (self::BlockStart | self::Blockquote)) == (self::BlockStart | self::Blockquote))
        {
            // If we are at the beginning of a blockquote, we consume all the whitespace between the blockquote
            // tag and the next markup token or text token that is not empty. That being said, if the number of
            // whitespaces is greater or equals than 4, we don't consume them, because those spaces make an
            // indent token
            $to_consume = 0;
            while ($this->peekCharAt($to_consume) == ' ')
                $to_consume++;

            if ($to_consume < 4)
                $this->index += $to_consume;
        }

        $c = $this->peekCharAt(0);
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

                if ($c . $this->peekCharAt(1) == '´')
                    return $this->checkItalic();            // ´

                return $this->checkLabeledListItem()        // a. , b. , A. , B. 
                        ?? $this->checkPreformatted()       // \t or \s{4}
                        ?? $this->checkCodeBlockLang()      // (string following a ```)
                        ?? $this->checkItalic();
        }
    }

    private function getNextTextToken() : ?Token
    {
        $c = NULL;
        $pc = NULL;

        $txt_length = 0;

        while (true)
        {
            // Update the previous occurrences
            if ($c !== NULL)
                $pc = $c;

            $c = $this->peekCharAt($txt_length);

            if ($c == NULL)
                break;
            
            // We need to check if the next character (or a string compound with
            // previous consumed chars) is a possible markup token, for that
            // we use the "stops" array with the markup tokens.
            // For every string length, we need to "go back" to check if there is a 
            // "token match" that could result in a markup token being processed
            $offset_back = -1;
            if (isset(self::TextStoppers[2][ $pc . $c ]))
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
                $prev_index = $this->index;
                $this->index += $txt_length - $offset_back;
                $tmp_token = $this->getNextMarkupToken();
                $this->index = $prev_index;
                if ($tmp_token !== NULL)
                {
                    // The token being not null means we need to move the pointer
                    // back to let the next call get it again, but we also need to
                    // remove the chars that belong to the next token that have been
                    // already consumed ($pc, $ppc, etc) and are present in the $txt variable
                    $txt_length -= $offset_back;
                    break;
                }
                // If the token is null, it means the character is a "stopper", but in the current
                // context, it is NOT a markup token (like a list token "-" that is not preceded by a 
                // valid starting block)
            }

            $txt_length++;         
        }

        if ($txt_length === 0)
            return NULL;

        return $this->emit(Token::Text, $txt_length);
    }

    private function checkHeader() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0          // Not a block start
            || ($this->flags & self::DoubleNewLine) != 0    // Previous double line
            || ($this->flags & self::StartOfInput) != 0)    // No previous elements to include in the header
            return NULL;

        $lookahead = $this->peekCharAt(0);

        if (!isset(Lexeme::Headers[$lookahead]))
            return NULL;

        $length = 0;
        while ($this->peekCharAt($length) === $lookahead)
            $length++;

        // Minimum of 4 characters
        if ($length < 4)
            return NULL;

        // We need to check the following char
        $tmp = $this->peekCharAt($length + 1);

        // To be a valid header, it needs to be the EOF or a NewLine
        if ($tmp !== Lexeme::NewLine && $tmp !== NULL)
            return NULL;

        return $this->emit(Lexeme::Headers[$lookahead], $length);
    }

    private function checkThematicBreak() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if ($this->accept(Lexeme::ThematicBreak, Lexeme::Lengths[Lexeme::ThematicBreak]))
            return $this->emit(Token::ThematicBreak, Lexeme::Lengths[Lexeme::ThematicBreak]);

        return NULL;
    }

    private function checkListItem() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if (isset(Lexeme::Lists[$this->peekCharAt(0)]) && $this->peekCharAt(1) === ' ')
            return $this->emit(Lexeme::Lists[$this->peekCharAt(0)], 2);

        return NULL;
    }

    private function checkTodoListItem() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if ($this->peekCharAt(3) == ' ' && $this->peekCharAt(0) == '[' && $this->peekCharAt(2) == ']')
        {
            $char = $this->peekCharAt(1);
            if ($char == 'x' || $char == 'X' || $char == ' ')
                return $this->emit(Token::TodoListItem, 4);
        }

        return NULL;
    }

    private function checkNumberedListItem() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        $length = 0;
        while (is_numeric($this->peekCharAt($length)))
            $length++;

        if ($length > 0 && $this->peekCharAt($length + 1) == ' ' && ($this->peekCharAt($length) == '.' || $this->peekCharAt($length) == ')'))
            return $this->emit(Token::NumberedListItem, $length + 2);

        return NULL;
    }

    private function checkLabeledListItem() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if (!ctype_alpha($this->peekCharAt(0)) || $this->peekCharAt(2) != ' ')
            return NULL;

        $delimiter = $this->peekCharAt(1);

        if ($delimiter == '.' || $delimiter == ')')
            return $this->emit(Token::LabeledListItem, 3);

        return NULL;
    }

    private function checkIndentation() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if ($this->peekCharAt() === Lexeme::Indent)
            return $this->emit(Token::Indentation, 1);

        if ($this->accept("    ", 4))
            return $this->emit(Token::Indentation, 4);

        return NULL;
    }

    private function checkPreformatted() : ?Token
    {
        $last = $this->lastToken();

        if ($last !== NULL && $last->type === Token::Indentation && $this->checkListItem() == NULL)
            return $this->emit(Token::Preformatted, 0);

        return NULL;
    }

    private function checkNewline() : ?Token
    {
        if ($this->peekCharAt(0) == Lexeme::NewLine)
        {
            $type = Token::NewLine;
            $length = 1;
            if ($this->peekCharAt(1) == Lexeme::NewLine)
            {
                $type = Token::DoubleNewLine;
                $length = 2;
            }

            return $this->emit($type, $length);
        }

        return NULL;
    }

    private function checkLt() : ?Token
    {
        if ($this->peekCharAt(0) === Lexeme::Lt)
            return $this->emit(Token::Lt, 1);

        return NULL;
    }

    private function checkBold() : ?Token
    {
        if ($this->peekCharAt(0) === Lexeme::BoldOpen)
            return $this->emit(Token::BoldOpen, 1);

        if ($this->peekCharAt(0) === Lexeme::BoldClose)
            return $this->emit(Token::BoldClose, 1);

        return NULL;
    }

    private function checkItalic() : ?Token
    {
        if ($this->accept(Lexeme::Italic, Lexeme::Lengths[Lexeme::Italic]))
            return $this->emit(Token::Italic, Lexeme::Lengths[Lexeme::Italic]);

        if ($this->accept(Lexeme::Italic2, Lexeme::Lengths[Lexeme::Italic2]))
            return $this->emit(Token::Italic, Lexeme::Lengths[Lexeme::Italic2]);

        return NULL;
    }

    private function checkInlineCode() : ?Token
    {
        if ($this->peekCharAt(0) === Lexeme::InlineCode)
            return $this->emit(Token::InlineCode, 1);

        return NULL;
    }        

    private function checkUnderlined() : ?Token
    {
        if ($this->accept(Lexeme::Underline, Lexeme::Lengths[Lexeme::Underline]))
            return $this->emit(Token::Underlined, Lexeme::Lengths[Lexeme::Underline]);

        return NULL;
    }

    private function checkPipe() : ?Token
    {
        if ($this->peekCharAt(0) === Lexeme::Pipe)
            return $this->emit(Token::Pipe, 1);

        return NULL;
    }

    private function checkReference() : ?Token
    {
        if (($this->flags & self::BlockStart) && $this->peekCharAt(0) == Lexeme::Reference)
            return $this->emit(Token::Reference, 1);

        return NULL;
    }

    private function checkColon() : ?Token
    {
        if ($this->peekCharAt(0) == Lexeme::Colon)
            return $this->emit(Token::Colon, 1);

        return NULL;
    }

    private function checkLink() : ?Token
    {
        $last = $this->lastToken();
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lastIsEscape)
            return NULL;

        if ($this->accept(Lexeme::LinkOpen, Lexeme::Lengths[Lexeme::LinkOpen]))
            return $this->emit(Token::LinkStart, Lexeme::Lengths[Lexeme::LinkOpen]);

        if ($this->accept(Lexeme::LinkClose, Lexeme::Lengths[Lexeme::LinkClose]))
            return $this->emit(Token::LinkEnd, Lexeme::Lengths[Lexeme::LinkClose]);

        return NULL;
    }

    private function checkImage() : ?Token
    {
        $last = $this->lastToken();
        $lastIsEscape = $last != NULL && $last->type === Token::Escape;

        if ($lastIsEscape)
            return NULL;

        if ($this->accept(Lexeme::ImgOpen, Lexeme::Lengths[Lexeme::ImgOpen]))
            return $this->emit(Token::ImageStart, Lexeme::Lengths[Lexeme::ImgOpen]);

        if ($this->accept(Lexeme::ImgClose, Lexeme::Lengths[Lexeme::ImgClose]))
            return $this->emit(Token::ImageEnd, Lexeme::Lengths[Lexeme::ImgClose]);

        return NULL;
    }

    private function checkStrikethrough() : ?Token
    {
        if ($this->accept(Lexeme::Strikethrough, Lexeme::Lengths[Lexeme::Strikethrough]))
            return $this->emit(Token::Strikethrough, Lexeme::Lengths[Lexeme::Strikethrough]);

        return NULL;
    }

    private function checkBlockquote() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        $length = 0;
        while ($this->peekCharAt($length) == Lexeme::Blockquote)
            $length ++;

        if ($length > 0)
            return $this->emit(Token::Blockquote, $length);

        return NULL;
    }

    private function checkEscapeBlock() : ?Token
    {
        $last = $this->lastToken();
        if ( ($last === NULL || $last->type != Token::Escape) && $this->accept(Lexeme::EscapeBlock, Lexeme::Lengths[Lexeme::EscapeBlock]))
            return $this->emit(Token::EscapeBlock, Lexeme::Lengths[Lexeme::EscapeBlock]);

        return NULL;
    }

    private function checkEscape() : ?Token
    {
        if ($this->peekCharAt(0) === Lexeme::Escape)
            return $this->emit(Token::Escape, 1);

        return NULL;
    }

    private function checkCodeBlock() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        // Make sure it is not a header
        if ($this->accept(Lexeme::Codeblock, Lexeme::Lengths[Lexeme::Codeblock]) && $this->peekCharAt(Lexeme::Lengths[Lexeme::Codeblock]) != $this->peekCharAt(0))
            return $this->emit(Token::CodeBlock, Lexeme::Lengths[Lexeme::Codeblock]);

        return NULL;
    }
    
    private function checkDmlCodeBlock() : ?Token
    {
        if (($this->flags & self::BlockStart) == 0)
            return NULL;

        if ($this->accept(Lexeme::DmlCodeblock, Lexeme::Lengths[Lexeme::DmlCodeblock]))
            return $this->emit(Token::CodeBlock, Lexeme::Lengths[Lexeme::DmlCodeblock]);

        return NULL;
    }

    private function checkCodeBlockLang() : ?Token
    {
        $last = $this->lastToken();

        if ($last === NULL || $last->type !== Token::CodeBlock)
            return NULL;

        $length = 0;
        $tmp = "";
        while (($tmp = $this->peekCharAt($length)) != NULL && $tmp != "\n")
            $length++;

        if ($length == 0)
            return NULL;

        return $this->emit(Token::CodeBlockLang, $length);
    }
}
