<?php

namespace Dml;

use Dml\Elements\Blockquote;
use Dml\Elements\Document;
use Dml\Elements\Element;
use Dml\Elements\Header;
use Dml\Elements\LineBreak;
use Dml\Elements\Paragraph;
use Dml\Elements\ThematicBreak;

class Parser
{
    /**
     * @var array<int, Closure>
     */
    private $blockParsers;

    /**
     * @var array<int, Closure>
     */
    private $inlineParsers;

    /**
     * @var \Dml\Token[]
     */
    private $tokens;

    /**
     * @var int
     */
    private $index;

    private $output;

    public function __construct()
    {
        $this->output = [];

        $this->blockParsers = [
            Token::HeaderStart    => $this->parseHeader,
            Token::Blockquote     => $this->parseBlockquote,
            Token::CodeBlock      => $this->parseCodeBlock,
            Token::Preformatted   => $this->parsePreformatted,
            Token::Indentation    => $this->parsePreformatted,
            Token::ListItem       => $this->parseList,
            Token::ThematicBreak  => $this->parseThematicBreak,
            Token::NewLine        => $this->parseNewLine,
        ];

        $this->inlineParsers = [
            Token::Text           => $this->parseText,
            Token::Reference      => $this->parseReference,
            Token::EscapeBlock    => $this->parseEscapeBlock,
            Token::Escape         => $this->parseEscape,
            Token::LinkStart      => $this->parseLink,
            Token::ImageStart     => $this->parseImage,
            Token::BoldOpen       => $this->parseBold,
            Token::Italic         => $this->parseItalic,
            Token::Underlined     => $this->parseUnderline,
            Token::Strikethrough  => $this->parseStrikethrough,
            Token::InlineCode     => $this->parseInlineCode,
            Token::NewLine        => $this->parseNewLine,
        ];
    }

    public function parse(string $source, ParsingContext $ctx = NULL) : Document
    {
        $lexer = new Lexer($source);
        $this->tokens = $lexer->tokenize();
        $this->index = 0;

        $document = $this->parseDocument($ctx ?? new ParsingContext());

        $this->tokens = NULL;
        $this->index = 0;

        return $document;
    }

    private function hasInput() : bool
    {
        return $this->tokens !== NULL && $this->index < count($this->tokens);
    }

    private function peekToken(int $offset = 0) : ?Token
    {
        if (!$this->hasInput() || $this->index + $offset > count($this->tokens))
            return NULL;

        return $this->tokens[$this->index + $offset];
    }

    private function consumeToken() : ?Token
    {
        if (!$this->hasInput())
            return NULL;

        return $this->tokens[$this->index++];
    }

    private function last(array $arr, $default = NULL)
    {
        $key = array_key_last($arr);

        if ($key === NULL)
            return $default;

        return $arr[$key];
    }

    private function getBlockParser(ParsingContext $ctx, Token $token)
    {
        if (isset($this->blockParsers[$token->type]))
            return $this->blockParsers[$token->type];

        return $this->parseParagraph;
    }

    private function getInlineParser(ParsingContext $ctx, Token $token, bool $escape = true)
    {
        if ($escape && $token->type == Token::Escape)
            return $this->parseEscape;

        if (!$ctx->isMarkupEnabled() || !isset($this->inlineParsers[$token->type]))
            return $this->parseText;
        
        return $this->inlineParsers[$token->type];
    }

    private function parseDocument(ParsingContext $ctx) : Document
    {
        // Get the block element parser and invoke the method
        // with the provided ParsingContext
        while ($this->hasInput())
        {
            if ($this->peekToken()->type == Token::DoubleNewLine)
            {
                $this->consumeToken();
                continue;
            }

            $this->getBlockParser($ctx, $this->peekToken())($ctx);
        }

        $doc = new Document();

        // Consume all the items that remain in the NodeStack
        // all of them are children of the DmlDocument object
        while (count($this->output) > 0)
            $doc->body->shiftChild(array_pop($this->output));

        return $doc;
    }

    private function parseParagraph(ParsingContext $ctx) : void
    {
        while ($this->hasInput())
        {

            // Break on 2NL
            if ($this->peekToken()->type == Token::DoubleNewLine)
            {
                $this->consumeToken();
                break;
            }

            $token = $this->peekToken();

            // If it is not an inline element we need to check
            // if we can process it with ParseText or just break the
            // loop.
            // If the token type cannot be parsed by a block parser, we
            // just add it as plain text, if not, we break the loop.
            if (!isset($this->inlineParsers[$token->type]))
            {
                if (!isset($this->blockParsers[$token->type]))
                {
                    $this->parseText($ctx);
                    continue;
                }
                break;
            }

            $this->inlineParsers[$token->type]($ctx);

            // If the token value ends with a dot and the next token we have to process is a NL
            // we need to add a line break to honor the grammatical paragraph
            $last = $this->last($this->output);
            $isParagraph = $last->type === Element::Paragraph;
            // FIXME: Fix grammatical paragraph
            $isGrammaticalParagraph = false;
            //$isGrammaticalParagraph = this.Output.Last.Value.InnerText.Trim()?.EndsWith(".") == true && $this->peekToken()?.Type == Token::NewLine;

            if ($isGrammaticalParagraph)
                $this->output[] = new LineBreak();
        }

        // Create new paragraph, add childs and add it to
        // the temporal output
        $paragraph = new Paragraph();

        // Because paragraphs can be divided by NewLines we need
        // to run nested loops to process them
        while (count($this->output) > 0)
        {
            // If next is a block element, we don't need to add a paragraph
            if ($this->last($this->output)->isBlockElement())
                break;

            // Add a child to the current paragrapg
            $paragraph->shiftChild(array_pop($this->output));
        }

        $this->output[] = $paragraph;
    }

    private function getBlockquoteLevel(Token $token) : int
    {
        if ($token->type !== Token::Blockquote)
            return -1;

        return strlen($token->value);
    }

    private function consumeWhiteSpaces() : void
    {
        while ($this->hasInput())
        {
            $token = $this->peekToken();

            if ($token->type !== Token::Text || strlen(trim($token->value)) > 0)
                break;

            $this->consumeToken();
        }
    }

    private function parseBlockquote(ParsingContext $ctx) : void
    {
        // Our ParseBlockquote needs to know the previos blockquote level and the target
        // level to work as expected
        $this->parseBlockquoteInLevel($ctx, $this->getBlockquoteLevel($this->peekToken()), 0);
    }

    private function parseBlockquoteInLevel(ParsingContext $ctx, int $targetLevel, int $previousLevel) : void
    {
        $blockquote = new Blockquote();

        // We can add the blockquote here, because of how the parsing method
        // is designed, we will not modify Output inside ParseBlockquote except
        // by current blockquote (this very line)
        $this->output[] = $blockquote;

        // While the target level is not the immediate next
        // level, resolve the next level first
        if ($targetLevel > $previousLevel + 1)
        {
            // This will parse the next level recursively until reach
            // $previousLevel == $targetLevel -1
            $this->parseBlockquote($ctx, $targetLevel, $previousLevel + 1);

            // Populate the current blockquote with the parsed child
            $blockquote->addChild(array_pop($this->output));
        }

        // If next token is not a blockquote, it means this blockquote
        // is finished, not need to parse anything else
        if (!$this->hasInput() || $this->peekToken()->type != Token::Blockquote)
            return;

        // Next token is a Blockquote, but we need to compute the current
        // level before continue
        $currentLevel = $this->getBlockquoteLevel($this->peekToken());

        // If the current level is less or equals than previous level
        // we don't need to make anything else here
        if ($currentLevel <= $previousLevel)
            return;

        // Here we start to parse the current block quote
        // Consume the blockquote token and clean the whitespaces
        $this->consumeToken();
        $this->consumeWhiteSpaces();

        // We compute the source of the blockquote, taking
        // the Token.OriginalValue or Token.Value, removing the
        // Blockquote tokens with the same nesting level, and
        // processing the children blockquote, that way we give
        // support to blockquotes to contain any type of markup
        // element
        $blockquoteSourceCode = "";

        // We will need a Parser
        $parser = new Parser();            

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // If we find a 2NL, we break the blockquote
            if ($token->type === Token::DoubleNewLine)
                break;

            // When the token is not a blockquote, we just need
            // to append the token's value to the StringBuilder
            if ($token->type !== Token::Blockquote)
            {
                $token = $this->consumeToken();
                $blockquoteSourceCode .= $token->originalValue ?? $token->value;
                continue;
            }

            // If it is a blockquote, we need to get the nesting level
            // of the blockquote
            $newLevel = $this->getBlockquoteLevel($token);

            // If the next level is lesser than the current one (its parent)
            // it means we need to close the current blockquote
            if ($newLevel < $currentLevel)
                break;

            // If the levels are equals, we just ignore
            // the Blockquote token and consume the whitespace
            // between the Blockquote token and the next one
            if ($newLevel == $currentLevel)
            {
                $this->consumeToken();
                $this->consumeWhiteSpaces();
                continue;
            }

            // Finally, if the next level is greater than the current one,
            // it means we found a child blockquote, we need to parse all the source
            // we found until this moment, add the processed nodes to the current
            // blockquote, process the child blockquote, and finally add it to the
            // current one
            if (strlen($blockquoteSourceCode) > 0)
            {
                $doc = $parser->parse($blockquoteSourceCode);
                foreach ($doc->children as $child)
                    $blockquote->addChild($child);
            }

            // Clear the SB as we already parsed the content
            $blockquoteSourceCode = "";

            // Process the child BQ
            $this->parseBlockquote($ctx, $newLevel, $currentLevel);

            // Add the child BQ to the current one
            $blockquote->addChild(array_pop($this->output));
        }

        // If there is source code available, parse the remaining source andd
        // add the children to the current blockquote
        if (strlen($blockquoteSourceCode) > 0)
        {
            $doc = $parser->parse($blockquoteSourceCode);
            foreach ($doc->children as $child)
                $blockquote->addChild($child);
        }
    }

    private function parseThematicBreak(ParsingContext $ctx) : void
    {
        $this->consumeToken();
        $this->output[] = new ThematicBreak();

        if (!$this->hasInput())
            return;

        // If next is a 2NL let caller handle it
        if ($this->peekToken()->type === Token::DoubleNewLine)
            return;

        // If just one new line is used, consume it
        if ($this->peekToken()->type === Token::NewLine)
            $this->consumeToken();
    }

    private function parseHeader(ParsingContext $ctx) : void
    {
        $token = $this->consumeToken();

        $headerType = Header::H1;

        switch ($token->value[0])
        {
            case '~':
                $headerType = Header::H2;
                break;
            case '-':
                $headerType = Header::H3;
                break;
            case '`':
                $headerType = Header::H4;
                break;
        }

        $header = new Header($headerType);

        // Take last's node children
        $header->takeChildren(array_pop($this->output));

        // FIXME: Fix the header ID logic, maybe in the generators
        // Build an id for the header
        //var id = header.InnerText.Replace(" ", "-").ToLower().Trim();
        //// TODO: Should we sanitize/modify something here?
        //header.Attributes["id"] = id;
        
        $this->output[] = $header;
    }
}
