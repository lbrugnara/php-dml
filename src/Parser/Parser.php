<?php

namespace Dml\Parser;

use Dml\Parser\Elements\Blockquote;
use Dml\Parser\Elements\Code;
use Dml\Parser\Elements\Document;
use Dml\Parser\Elements\Element;
use Dml\Parser\Elements\Group;
use Dml\Parser\Elements\Header;
use Dml\Parser\Elements\Image;
use Dml\Parser\Elements\Italic;
use Dml\Parser\Elements\LineBreak;
use Dml\Parser\Elements\Link;
use Dml\Parser\Elements\ListItem;
use Dml\Parser\Elements\OrderedList;
use Dml\Parser\Elements\Paragraph;
use Dml\Parser\Elements\Preformatted;
use Dml\Parser\Elements\Reference;
use Dml\Parser\Elements\ReferenceGroup;
use Dml\Parser\Elements\ReferenceLink;
use Dml\Parser\Elements\Strike;
use Dml\Parser\Elements\Strong;
use Dml\Parser\Elements\Text;
use Dml\Parser\Elements\ThematicBreak;
use Dml\Parser\Elements\TodoList;
use Dml\Parser\Elements\TodoListItem;
use Dml\Parser\Elements\Underline;
use Dml\Parser\Elements\UnorderedList;

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
     * Parsed elements
     *
     * @var \Dml\Parser\Elements\Element[]
     */
    private $output;

    /**
     * Lexer
     *
     * @var \Dml\Parser\Lexer
     */
    private $lexer;    

    public function __construct()
    {
        $this->output = [];

        $this->blockParsers = [
            Token::HeaderStart    => [ $this, 'parseHeader' ],
            Token::Blockquote     => [ $this, 'parseBlockquote' ],
            Token::CodeBlock      => [ $this, 'parseCodeBlock' ],
            Token::Preformatted   => [ $this, 'parsePreformatted' ],
            Token::Indentation    => [ $this, 'parsePreformatted' ],
            Token::ListItem       => [ $this, 'parseList' ],
            Token::ThematicBreak  => [ $this, 'parseThematicBreak' ],
            Token::NewLine        => [ $this, 'parseNewLine' ],
        ];

        $this->inlineParsers = [
            Token::Text           => [ $this, 'parseText' ],
            Token::Reference      => [ $this, 'parseReference' ],
            Token::EscapeBlock    => [ $this, 'parseEscapeBlock' ],
            Token::Escape         => [ $this, 'parseEscape' ],
            Token::LinkStart      => [ $this, 'parseLink' ],
            Token::ImageStart     => [ $this, 'parseImage' ],
            Token::BoldOpen       => [ $this, 'parseBold' ],
            Token::Italic         => [ $this, 'parseItalic' ],
            Token::Underlined     => [ $this, 'parseUnderline' ],
            Token::Strikethrough  => [ $this, 'parseStrikethrough' ],
            Token::InlineCode     => [ $this, 'parseInlineCode' ],
            Token::NewLine        => [ $this, 'parseNewLine' ],
        ];
    }

    public function parse(string $source, ParsingContext $ctx = NULL) : Document
    {
        $this->lexer = new Lexer($source);

        $document = $this->parseDocument($ctx ?? new ParsingContext());

        return $document;
    }

    private function hasInput() : bool
    {
        return $this->lexer->hasInput();
    }

    private function peekToken(int $offset = 0) : ?Token
    {
        return $this->lexer->peekToken($offset);
    }

    private function consumeToken() : ?Token
    {
        return $this->lexer->nextToken();
    }

    private function getLastElement(array $arr, $default = NULL) : ?Element
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

        return [ $this, 'parseParagraph' ];
    }

    private function getInlineParser(ParsingContext $ctx, Token $token, bool $escape = true)
    {
        if ($escape && $token->type === Token::Escape)
            return [ $this, 'parseEscape' ];

        if (!$ctx->isMarkupEnabled() || !isset($this->inlineParsers[$token->type]))
            return [ $this, 'parseText' ];
        
        return $this->inlineParsers[$token->type];
    }

    private function parseDocument(ParsingContext $ctx) : Document
    {
        // Get the block element parser and invoke the method
        // with the provided ParsingContext
        while ($this->hasInput())
        {
            if ($this->peekToken()->type === Token::DoubleNewLine)
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
            $doc->unshiftChild(array_pop($this->output));

        return $doc;
    }

    private function parseParagraph(ParsingContext $ctx) : void
    {
        while ($this->hasInput())
        {

            // Break on 2NL
            if ($this->peekToken()->type === Token::DoubleNewLine)
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

            $textElement = $this->findLastTextElement($this->getLastElement($this->output));

            if ($textElement !== NULL)
            {
                $it = trim($textElement->content);
                $length = strlen($it);
                if ($length > 0 && $it[$length-1] == '.')
                    $this->output[] = new LineBreak();
            }
        }

        // Create new paragraph, add childs and add it to
        // the temporal output
        $paragraph = new Paragraph();

        // Because paragraphs can be divided by NewLines we need
        // to run nested loops to process them
        while (count($this->output) > 0)
        {
            // If next is a block element, we don't need to add a paragraph
            if ($this->getLastElement($this->output)->isBlockElement())
                break;

            // Add a child to the current paragrapg
            $paragraph->unshiftChild(array_pop($this->output));
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
        if (!$this->hasInput() || $this->peekToken()->type !== Token::Blockquote)
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
        // the token->OriginalValue or token->Value, removing the
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
                foreach ($doc->getChildren() as $child)
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
            foreach ($doc->getChildren() as $child)
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
        $header->takeChildrenFrom(array_pop($this->output));        
        
        $this->output[] = $header;
    }

    private function parseCodeBlock(ParsingContext $ctx) : void
    {
        if (!$this->hasInput())
            return;

        // If the CodeBlock is a DmlSource code block, leave this basic code block
        if ($this->peekToken(1)->type === Token::CodeBlockLang && $this->peekToken(1)->value === "dml-source")
        {
            $this->parseDmlSource($ctx);
            return;
        }

        // Get a reference to the last node in the linked list
        // before doing anything related to this inline element
        $lastNode = $this->getLastElement($this->output);

        // Save the starting token
        $startToken = $this->consumeToken();

        // If the code block starts with !```, it is a code block that
        // allows markup processing, if not, it is a basic block code.
        // Save the current state of the markup processing
        $oldMarkupProcessingState = $ctx->setMarkupStatus($startToken->value[0] === "!");

        $token = $this->consumeToken(); // Consume the NL or the CodeBlockLang

        // If next token is the CodeBlockLang, consume it and save
        // the value to be used in the class attribute
        $lang = NULL;
        if ($token->type === Token::CodeBlockLang)
        {
            $this->consumeToken(); // NL
            $lang = $token->value;
        }

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // End the loop if the closing token is found
            if ($token->type === Token::CodeBlock)
            {
                $this->consumeToken();
                break;
            }

            // If the escaped token is a CodeBlock, ignore the Escape token and consume the CodeBlock
            if ($token->type === Token::Escape && $this->peekToken(1)->type === Token::CodeBlock)
            {
                $this->consumeToken(); // Ignore Escape
                $token = $this->consumeToken(); // Consume CodeBlock
            }

            // If the markup is not enabled or there's no inline element parser for the token type, use ParseText.
            $this->getInlineParser($ctx, $token, false)($ctx);
        }

        // Create a CodeNode that will be rendered as a block
        $codeNode = new Code(true);

        // Set the class if lang is available
        if ($lang !== NULL)
            $codeNode->attributes["class"] = $lang;

        while (count($this->output) > 0)
        {
            // If the last node is equals to our saved lastNode, it means we reached
            // the starting point so we need to stop consuming Output's elements
            if ($this->getLastElement($this->output) === $lastNode)
                break;

            // Add a child to the current code node
            $codeNode->unshiftChild(array_pop($this->output));
        }

        // Wrap the CodeNode with a PreformattedNode
        $pre = new Preformatted();
        $pre->addChild($codeNode);

        // Add the node to the Output
        $this->output[] = $pre;

        // Restore the previous state
        $ctx->setMarkupStatus($oldMarkupProcessingState);
    }

    private function parseDmlSource(ParsingContext $ctx) : void
    {
        $this->consumeToken(); // Consume CodeBlock
        $this->consumeToken(); // Consume CodeBlockLang
        $this->consumeToken(); // Consume NewLine

        // This will contain all the tokens withing the code block
        $source = [];

        while ($this->hasInput())
        {
            $token = $this->consumeToken();

            // Break if we found the closing token
            if ($token->type === Token::CodeBlock)
                break;

            // If the escaped token is a CodeBlock, ignore the Escape and take the CodeBlock
            if ($token->type === Token::Escape && $this->peekToken()->type === Token::CodeBlock)
                $token = $this->consumeToken();

            $source[] = $token;
        }

        // Source
        $code = new Code(true);

        // Process the source as plain text
        foreach ($source as $token)
        {
            $code->addChild(new Text(str_replace("<", "&lt;", ($token->originalValue ?? $token->value))));

        }

        // Add the CodeBlock with the source
        $this->output[] = $code;

        // Process previous source to get the rendered version
        $parser = new Parser();
        $source_str = "";
        foreach ($source as $token)
            $source_str .= $token->originalValue ?? $token->value;
        $doc = $parser->parse($source_str);

        // Get body's children of the parsed document
        foreach ($doc->getChildren() as $child)
            $this->output[] = $child;
            
        // Add a <hr/> after the DmlSource
        $hr = new ThematicBreak();
        $hr->attributes["class"] = "short";

        $this->output[] = $hr;
    }

    private function parsePreformatted(ParsingContext $ctx) : void
    {
        // Get a reference to the last node in the linked list
        // before doing anything related to this preformatted block
        $lastNode = $this->getLastElement($this->output);

        // Consume the Preformatted token
        $this->consumeToken();

        // Disable markup processing. Save the current markup processing state
        $oldMarkupProcessingState = $ctx->setMarkupStatus(false);

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Break on 2NL
            if ($token->type === Token::DoubleNewLine)
                break;

            // Consume the indentation after a new line
            if ($token->type === Token::NewLine && $this->peekToken(1)->type === Token::Indentation)
            {
                $this->consumeToken();
                $this->consumeToken();
                $this->output[] = new Text("\n");
                continue;
            }

            // It is always ParseText
            $this->parseText($ctx);
        }

        // Restore markup processing
        $ctx->setMarkupStatus($oldMarkupProcessingState);
        
        $code = new Code(true);

        // Populate the CodeNode
        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $code->unshiftChild(array_pop($this->output));

        // Wrap the code node into the pref node
        $pre = new Preformatted();

        $pre->addChild($code);

        // Add the pre node into the output
        $this->output[] = $pre;
    }

    private function getListType(Token $listToken) : int
    {
        return $listToken->value === "# " 
                ? Element::OrderedList
                : ($listToken->value[0] == '['
                    ? Element::TodoList
                    : Element::UnorderedList);
    }

    private function getOrderedListStartIndex(Token $token) : ?int
    {
        if ($token->originalValue == NULL || strlen($token->originalValue) <= 2)
            return NULL;

        $value = substr($token->originalValue, 0, strlen($token->originalValue) - 2);

        if (is_numeric($value))
            return intval($value);

        return NULL;
    }

    private function isSameListType(Token $a, Token $b) : bool
    {
        return $a->type == $b->type && $a->value[0] == $b->value[0] && ($a->originalValue == $b->originalValue || $a->originalValue != NULL);
    }

    private function parseList(ParsingContext $ctx) : void
    {
        // Get a reference to the last node in the linked list
        // before doing anything related to this list
        $lastNode = $this->getLastElement($this->output);

        // Track the indents for nested lists
        // indents contains the current list's indentation
        $indents = 0;
        
        while ($this->peekToken($indents)->type === Token::Indentation)
            $indents++;

        // This tokens contains the type of list (we step over '$indents' tokens)
        $listTypeToken = $this->peekToken($indents);

        // Compute the ListType
        $listType = $this->getListType($listTypeToken);

        // Compute the start index if the list is an Ordered list
        $listStartIndex = $listType == Element::OrderedList ? $this->getOrderedListStartIndex($listTypeToken) : NULL;

        // Use a flag to know if this current list is an enumerated list
        $isEnumeratedList = $listStartIndex !== NULL;

        // Keep track of the last index (enumerated lists)
        $lastIndex = NULL;

        while ($this->hasInput())
        {
            // Check the current indentation level
            $currentIndents = 0;
            while ($this->peekToken($currentIndents)->type === Token::Indentation)
                $currentIndents++;

            $token = $this->peekToken($currentIndents);

            // This tokens must be a list item
            if ($token->type !== Token::ListItem)
                break;

            // If $currentIndents is lesser than the original $indents, we need to go
            // back and close this list
            if ($currentIndents < $indents)
                break;

            // Get the current token's list type
            $currentType = $this->getListType($token);

            // If the list type changes, and we are on the same indentation level, we need to
            // close this list to start a new one that will be sibling of this one
            if (!$this->isSameListType($listTypeToken, $token) && $currentIndents == $indents)
                break;

            // Ordered lists might break if they are "numerated"
            if ($isEnumeratedList && $currentIndents == $indents)
            {
                // Check if $lastIndex is poupulted, first time it is NULL
                // Check if $currentType is ordered too
                if ($lastIndex !== NULL && $currentType === Element::OrderedList)
                {
                    // Compute the currentIndex
                    $currentIndex = $this->getOrderedListStartIndex($token);

                    // If the $currentIndex exists is not $lastIndex + 1, break this list
                    if ($currentIndex === NULL || $currentIndex <= $lastIndex || $currentIndex > $lastIndex + 1)
                        break;
                }

                // Update last index
                $lastIndex = $this->getOrderedListStartIndex($token);
            }

            // If the indent level is the same, just add a new <li> to the list
            if ($currentIndents == $indents)
            {
                $this->parseListItem($ctx);
                continue;
            }

            // If not, it means the $currentIndents is greater than the original $indents.
            // In that case we need to parse a new list that will be child of the last
            // saved <li> element
            $this->parseList($ctx);

            // Retrieve the parsed list
            $innerList = array_pop($this->output);

            // Append the inner list to the last <li>
            $this->getLastElement($this->output)->addChild($innerList);
        }

        // Create the ListNode and populate with the ListItemNodes
        $list = NULL;
        
        if ($listType === Element::OrderedList)
            $list = new OrderedList();
        else if ($listType === Element::TodoList)
            $list = new TodoList();
        else
            $list = new UnorderedList();

        // If the list is enumerated, set the start index
        if ($listStartIndex !== NULL)
        {
            $list->attributes["start"] = strval($listStartIndex);
            $list->properties["index"] = $listStartIndex;
        }

        $list->properties["indents"] = $indents;

        // Process list's children
        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $list->unshiftChild(array_pop($this->output));

        // Add the list to the output
        $this->output[] = $list;
    }

    private function findLastTextElement(?Element $element) : ?Element
    {
        if ($element == NULL)
            return NULL;

        $tmp = $element;
        do {
            if ($tmp instanceof Text)
                return $tmp;

            $tmp = $this->getLastElement($tmp->getChildren());
        } while ($tmp !== NULL);

        return NULL;
    }

    private function parseListItem(ParsingContext $ctx) : void
    {
        // Get a reference to the last node in the linked list
        // before doing anything related to this list
        $lastNode = $this->getLastElement($this->output);

        // Each list item is responsible of removing the indentation
        while ($this->peekToken()->type === Token::Indentation)
            $this->consumeToken();

        // Retrieve the token that contains the list type info
        $listToken = $this->consumeToken();

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Break on ListItem or Indentation to let ParseList parse the new items
            // Break on DoubleNewLine to let some caller in the chain to handle it
            if ($token->type === Token::ListItem || $token->type === Token::DoubleNewLine || $token->type === Token::Indentation)
                break;

            // Just one new line means the content is still part of the current item, consume it and continue
            if ($token->type === Token::NewLine)
            {
                $this->consumeToken();

                $textElement = $this->findLastTextElement($this->getLastElement($this->output));

                if ($textElement !== NULL)
                {
                    $it = trim($textElement->content);
                    $length = strlen($it);
                    if ($length > 0 && $it[$length-1] == '.')
                        $this->output[] = new LineBreak();
                }

                continue;
            }

            // Parse the inline element
            $this->getInlineParser($ctx, $token)($ctx);
        }

        // Todo items use a different implementation
        $listItem = $this->getListType($listToken) === Element::TodoList 
                        ? new TodoListItem(strlen($listToken->value) > 1 && $listToken->value[0] == '[' && strtolower($listToken->value)[1] == 'x')
                        : new ListItem();

        // Todos already have children, we need to get the base index to insert new nodes
        $baseIndex = count($listItem->getChildren());

        // Populate the ListItem
        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $listItem->insertChild($baseIndex, array_pop($this->output));

        // Add the ListItem to the Output
        $this->output[] = $listItem;
    }

    private function parseEscapeBlock(ParsingContext $ctx) : void
    {
        // Consume the starting token ``
        $this->consumeToken();

        // // Disable the markup processing. Save the markup processing state
        $oldMarkupProcessingState = $ctx->setMarkupStatus(false);

        while ($this->hasInput())
        {
            $token = $this->PeekToken();

            // Break when we found the closing token ``
            if ($token->type === Token::EscapeBlock)
            {
                $this->consumeToken();
                break;
            }

            // Always process the content as plain text
            $this->parseText($ctx);
        }

        // Restore the markup processing state
        $ctx->setMarkupStatus($oldMarkupProcessingState);
    }

    private function parseNewLine(ParsingContext $ctx) : void
    {
        $this->output[] = new Text($this->consumeToken()->value);
    }

    private function parseBold(ParsingContext $ctx) : void
    {
        $this->parseInline($ctx, new Strong(), Token::BoldClose);
    }

    private function parseItalic(ParsingContext $ctx) : void
    {
        $this->parseInline($ctx, new Italic(), Token::Italic);
    }

    private function parseUnderline(ParsingContext $ctx) : void
    {
        $this->parseInline($ctx, new Underline(), Token::Underlined);
    }

    private function parseStrikethrough(ParsingContext $ctx) : void
    {
        $this->parseInline($ctx, new Strike(), Token::Strikethrough);
    }

    private function parseInlineCode(ParsingContext $ctx) : void
    {
        // Lookahead to search for the "ending" token, if it is not present, the "InlineCode" we found
        // is [not] an inline code tag
        $tmp = NULL;
        $i = 0;
        while (($tmp = $this->peekToken(++$i)) != NULL)
        {
            if ($tmp->type === Token::InlineCode || $tmp->type === Token::DoubleNewLine)
                break;
        }

        if ($tmp == NULL || $tmp->type === Token::DoubleNewLine)
        {
            $this->parseText($ctx);
            return;
        }

        // At this point we made sure it is a valid inline code tag
        $oldMarkupProcessingMode = $ctx->setMarkupStatus(false);

        $this->parseInline($ctx, new Code(false), Token::InlineCode);

        $ctx->setMarkupStatus($oldMarkupProcessingMode);
    }

    private function parseInline(ParsingContext $ctx, Element $element, int $close) : void
    {
        // Get a reference to the last node in the linked list
        // before doing anything related to this inline element
        $lastNode = $this->getLastElement($this->output);

        // Keep the start token, we could need it 
        $startToken = $this->consumeToken();

        while (true)
        {
            // If we run out of tokens, we need to place the starting token after lastNode (starting point).
            // If lastNode is NULL, it means we don't have elements in the Output, so place the start token
            if (!$this->hasInput())
            {
                if ($lastNode != NULL)
                {
                    $key = array_search($lastNode, $this->output, true);
                    array_splice($this->output, $key + 1, 0, [ new Text($startToken->value) ]);
                }
                else
                {
                    array_unshift($this->output, new Text($startToken->value));
                }
                return;
            }

            // Keep parsing more inline elements
            $token = $this->peekToken();

            // The 2-NL rule is handled at block elements, so if we find two new lines
            // we need to return to the caller, but because the 2-NL will end the current
            // element, it means it is not a valid "inline" element, just plain text.
            // We insert a new TextNode with the starting $token's value after our
            // $lastNode
            if ($token->type === Token::DoubleNewLine)
            {
                $key = array_search($lastNode, $this->output, true);
                array_splice($this->output, $key + 1, 0, [ new Text($startToken->value) ]);
                return;
            }

            // If next $token is the one that closes this element, consume the $token
            // and break the loop
            if ($token->type === $close)
            {
                $this->consumeToken();
                break;
            }

            $this->getInlineParser($ctx, $token)($ctx);
        }

        // If the last node is equals to our saved $lastNode, it means we reached
        // the starting point so we need to stop consuming Output's elements
        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
        {
            // Add a child to the current paragrapg
            $element->unshiftChild(array_pop($this->output));
        }

        // Add the parsed element
        $this->output[] = $element;
    }

    private function parseLink(ParsingContext $ctx) : void
    {
        // Get a reference to the first token before any link's token
        $lastNode = $this->getLastElement($this->output);

        // Consume the start token, we might need it if this is not
        // a valid link
        $startToken = $this->consumeToken();

        // We need to parse the link's content, but if it is empty
        // we won't create a LinkNode
        $isValidLink = false;

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Pipe divide link's sections
            if ($token->type === Token::Pipe)
            {
                $this->consumeToken();
                break;
            }

            // If we find the LinkEnd, we need to break
            if ($token->type === Token::LinkEnd || $token->type === Token::DoubleNewLine)
                break;

            $this->getInlineParser($ctx, $token)($ctx);

            // Check if the link's content is not empty
            $isValidLink |= $token->type !== Token::Text || strlen(trim($token->value)) > 0;
        }

        // We have content, we have a link. Now we need to parse (if available)
        // the href and title attributes
        $href = "";
        $title = "";

        // Href
        while ($this->hasInput())
        {
            $token = $this->peekToken();

            if ($token->type === Token::Pipe)
            {
                $this->consumeToken();
                break;
            }

            if ($token->type === Token::LinkEnd || $token->type === Token::DoubleNewLine)
                break;

            $href .= $this->consumeToken()->value;
        }

        // Title
        while ($this->hasInput())
        {
            $token = $this->peekToken();

            if ($token->type === Token::LinkEnd || $token->type === Token::DoubleNewLine)
                break;

            $title .= $this->consumeToken()->value;
        }


        // If next $token is not a LinkEnd, it is not a valid link
        $isValidLink &= $this->peekToken()->type === Token::LinkEnd;


        // If the final $token is not a LinkEnd, it is not
        // a link, we need to return the parsed content
        if (!$isValidLink)
        {
            // Add the starting $token as plain text
            $key = array_search($lastNode, $this->output, true);
            array_splice($this->output, $key + 1, 0, [ new Text($startToken->value) ]);

            // We need to parse the href and title attributes, because
            // we consumed them as plain text before
            $parser = new Parser();

            // Parse the href
            $href_doc = $parser->parse($href);
            
            if (count($href_doc->getChildren()) > 0)
            {
                $this->output[] = new Text("|");
                $href_doc_children = $href_doc->getChildren();
                
                if (count($href_doc_children) > 0)
                {
                    foreach ($href_doc_children[0]->getChildren() as $child)
                        $this->output[] = $child;
                }
            }

            // Parse the title
            $title_doc = $parser->parse($title);

            if (count($title_doc->getChildren()) > 0)
            {
                $this->output[] = new Text("|");
                $title_doc_children = $title_doc->getChildren();
                
                if (count($title_doc_children) > 0)
                {
                    foreach ($title_doc_children[0]->getChildren() as $child)
                        $this->output[] = $child;
                }
            }                    

            return;
        }

        // Consume the LinkEnd $token
        $this->consumeToken();

        // Check if the link is a link to a reference
        $href = trim($href);
        if (isset($href[0]) && $href[0] == ':')
        {
            $titles = explode(',', trim($title));
            $hrefs = explode(',' ,trim(substr($href, 1)));

            $referenceGroup = new ReferenceGroup();

            while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
                $referenceGroup->unshiftChild(array_pop($this->output));

            for ($i = 0; $i < count($hrefs); $i++)
            {
                $refLink = new ReferenceLink($hrefs[$i], isset($titles[$i]) ? $titles[$i] : NULL);
                $referenceGroup->addChild($refLink);
            }

            $this->output[] = $referenceGroup;

            return;
        }

        // If it is not a link to a reference, it is a simple
        // anchor
        $link = new Link($href, $title);

        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $link->unshiftChild(array_pop($this->output));

        $this->output[] = $link;
    }

    private function parseImage(ParsingContext $ctx) : void
    {
        // Get a reference to the first token before any img's token
        $lastNode = $this->getLastElement($this->output);

        // Consume the start token, we might need it if this is not
        // a valid image
        $startToken = $this->consumeToken();

        $isValidImage = true;

        // src
        $source = "";

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Pipe close the title section, consume it and break
            if ($token->type === Token::Pipe)
            {
                $this->consumeToken();
                break;
            }

            // Image end or 2NL break, because of end of image or invalid image
            if ($token->type === Token::ImageEnd || $token->type === Token::DoubleNewLine)
                break;

            $source .= $this->consumeToken()->value;
        }

        // If src is empty, it is not a valid img tag
        if (strlen(trim($source)) === 0)
            $isValidImage = false;

        // title
        $title = "";

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Pipe close the title section, consume it and break
            if ($token->type === Token::Pipe)
            {
                $this->consumeToken();
                break;
            }

            // Image end or 2NL break, because of end of image or invalid image
            if ($token->type === Token::ImageEnd || $token->type === Token::DoubleNewLine)
                break;

            $title .= $this->consumeToken()->value;
        }

        // alt
        $altTitle = "";
        
        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Image end or 2NL break, because of end of image or invalid image
            if ($token->type === Token::ImageEnd || $token->type === Token::DoubleNewLine)
                break;

            $altTitle .= $this->consumeToken()->value;
        }


        // If next token is not the ImageEnd, it is an invalid img tag
        $isValidImage &= $this->hasInput() && $this->peekToken()->type === Token::ImageEnd;


        // If the final token is not a ImageEnd, it is not
        // an image, we need to return the parsed content
        if (!$isValidImage)
        {
            // Add the starting token as plain text
            $key = array_search($lastNode, $this->output, true);
            array_splice($this->output, $key + 1, 0, [ new Text($startToken->value) ]);

            // We need to parse the srouce, title, and alt title attributes, because
            // we consumed them as plain text before
            $parser = new Parser();

            // Parse the source
            $src_doc = $parser->parse($source);

            if (count($src_doc->getChildren()) > 0)
            {
                $src_doc_children = $src_doc->getChildren();
                
                if (count($src_doc_children) > 0)
                {
                    foreach ($src_doc_children[0]->getChildren() as $child)
                        $this->output[] = $child;
                }
            }

            // Parse the title
            $title_doc = $parser->parse($title);

            if (count($title_doc->getChildren()) > 0)
            {
                $this->output[] = new Text("|");

                $title_doc_children = $title_doc->getChildren();
                
                if (count($title_doc_children) > 0)
                {
                    foreach ($title_doc_children[0]->getChildren() as $child)
                        $this->output[] = $child;
                }
            }

            // Parse the alt title
            $alt_title_doc = $parser->parse($altTitle);

            if (count($$alt_title_doc->getChildren()) > 0)
            {
                $this->output[] = new Text("|");

                $alt_title_doc_children = $alt_title_doc->getChildren();
                
                if (count($alt_title_doc_children) > 0)
                {
                    foreach ($alt_title_doc_children[0]->getChildren() as $child)
                        $this->output[] = $child;
                }
            }

            return;
        }

        // Consume }]
        $this->consumeToken();

        $img = new Image($title, $source, $altTitle);

        // Add the image to the output
        $this->output[] = $img;
    }

    private function parseReference(ParsingContext $ctx) : void
    {
        // Save the last inserted node before any
        // Reference processing
        $lastNode = $this->getLastElement($this->output);

        // Consume and save the startToken, we might need it later
        $startToken = $this->consumeToken();

        // We might need th colon token
        $colonToken = NULL;

        $validReference = true;

        // Parse the content between | and : (href)
        $href = "";

        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // If it is a DoubleNewLine, it is an invalid reference
            if ($token->type === Token::DoubleNewLine)
            {
                $validReference = false;
                break;
            }

            // Break at colon
            if ($token->type === Token::Colon)                    
                break;

            // Concatenate all tokens as plain text (it is a HTML attribute's value)
            $href .= $this->consumeToken()->value;
        }

        if ($this->peekToken()->type !== Token::Colon)
            $validReference = false;
        else
            $colonToken = $this->consumeToken();

        // If there is no more input, it is not a valid reference
        $validReference &= $this->hasInput();
        
        // We need to parse the Reference's title
        while ($validReference && $this->hasInput())
        {
            $token = $this->peekToken();

            // Break on 2NL rule and mark the parsing as invalid
            if ($token->type === Token::DoubleNewLine)
            {
                $validReference = false;
                break;
            }

            // Break on reference end
            if ($token->type === Token::Pipe)                    
                break;

            $this->getInlineParser($ctx, $token)($ctx);
        }

        // If the next token is not a ReferenceEnd, it is not a valid reference
        if ($this->peekToken()->type !== Token::Pipe)
            $validReference = false;

        // We need to rollback the parsing
        if (!$validReference)
        {
            // Insert the pipe as plain text
            $key = array_search($lastNode, $this->output, true);
            array_splice($this->output, $key + 1, 0, [ new Text($startToken->value) ]);

            // Parse the href, it might contain markup elements
            $parser = new Parser();
            $href_doc = $parser->parse($href);

            if (count($href_doc->getChildren()) > 0)
            {
                $this->output[] = new Text("|");
                $href_doc_children = $href_doc->getChildren();
                
                if (count($href_doc_children) > 0)
                {
                    foreach ($href_doc_children[0]->getChildren() as $child)
                    {
                        $key = array_search($lastNode, $this->output, true);
                        array_splice($this->output, $key + 1, 0, [ $child ]);
                    }
                }
            }

            // If $colonToken has been found, we need to insert it in the right position
            if ($colonToken != NULL)
            {
                array_splice($this->output, $key + 1, 0, [ $colonToken->value ]);
            }

            return;
        }

        // Consume the ReferenceEnd
        $this->consumeToken();

        // Create the new ReferenceNode
        $reference = new Reference(trim($href));

        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $reference->unshiftChild(array_pop($this->output));

        // Add the reference
        $this->output[] = $reference;

        // Get a reference to the first token before any img's token
        $lastNode = $this->getLastElement($this->output);

        // Process the reference siblings, "logically" we consider it a block (see below)
        while ($this->hasInput())
        {
            $token = $this->peekToken();

            // Break on 2NL
            if ($token->type === Token::DoubleNewLine)
            {
                break;
            }
            // Break on Reference to "close" de block
            else if ($token->type === Token::Reference)
            {
                break;
            }
            

            // If it is not an inline element we need to check
            // if we can process it with ParseText or just break the
            // loop.
            // If the token type cannot be parsed by a block parser, we
            // just add it as plain text, if not, we break the loop.
            if (!isset($this->inlineParsers[$token->type]))
            {
                if (!isset($this->blockElementParsers[$token->type]))
                {
                    $this->parseText($ctx);
                    continue;
                }
                break;
            }

            $this->inlineParsers[$token->type]($ctx);
        }

        // Create new paragraph, add childs and add it to
        // the temporal output
        $referenceSiblings = new Group();


        while (count($this->output) > 0 && $this->getLastElement($this->output) !== $lastNode)
            $referenceSiblings->unshiftChild(array_pop($this->output));

        $this->output[] = $referenceSiblings;

        // Even though references are treated like inline elements, we add a line break
        // after them to simulate block behavior. The advantage of this hack is to give them 
        // a "list-like" format without the need of breaking the references into multiple
        // paragraphs by considering them block elements or by separating them with double new lines
        // nor force users to add a dot to trigger grammatical paragraphs
        $this->output[] = new LineBreak();
    }

    private function parseEscape(ParsingContext $ctx) : void
    {
        $this->consumeToken();

        // If the escaped token is not an especial token
        // we just add a backslash
        if ($this->peekToken()->type === Token::Text)
        {
            $this->output[] = new Text("\\");
        }
        else if ($this->peekToken()->type === Token::Lt)
        {
            $this->consumeToken();
            $this->output[] = new Text("&lt;");
        }
        else
        {
            // If the token's type is different from Text, we process
            // the token's value as plain text
            $this->parseText($ctx);
        }
    }

    private function parseText(ParsingContext $ctx) : void
    {
        $token = $this->consumeToken();
        $value = $token->value;

        // Sanitize the '<'
        if (!$ctx->isMarkupEnabled())
            $value = str_replace("<", "&lt;", $value ?? "");

        $this->output[] = new Text($value);
    }
}
