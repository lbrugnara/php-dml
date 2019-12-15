<?php

namespace Dml\Elements;

class Element
{
    const Blockquote = 0;
    const CodeBlock = 1;
    const Custom = 2;
    const InlineCode = 3;
    const Document = 4;
    const Group = 5;
    const Header = 6;
    const ThematicBreak = 7;
    const Image = 8;
    const Italic = 9;
    const Link = 10;
    const UnorderedList = 11;
    const OrderedList = 12;
    const TodoList = 13;
    const ListItem = 14;
    const LineBreak = 15;
    const Paragraph = 16;
    const Preformatted = 17;
    const ReferenceGroup = 18;
    const ReferenceLink = 19;
    const Reference = 20;
    const Strike = 21;
    const Strong = 22;
    const Text = 23;
    const Underlined = 24;

    /**
     * @var array<string, string>
     */
    public $attributes;

    /**
     * @var array<string, mixed>
     */
    public $properties;

    /**
     * @var null|\Dml\Elements\Element
     */
    public $parent;

    /**
     * @var \Dml\Elements\Element[]
     */
    protected $children;

    /**
     * @var int
     */
    public $type;

    public function __construct()
    {
        $this->properties = [];
        $this->attributes = [];
    }

    public function isBlockElement() : bool
    {
        return $this->type === Element::Blockquote
            || $this->type === Element::CodeBlock
            || $this->type === Element::Document
            || $this->type === Element::Header
            || $this->type === Element::UnorderedList
            || $this->type === Element::OrderedList
            || $this->type === Element::TodoList
            || $this->type === Element::ThematicBreak
            || $this->type === Element::Paragraph
            || $this->type === Element::Preformatted;
    }

    public function isInlineElement() : bool
    {
        return $this->type == Element::Custom
            || $this->type == Element::InlineCode
            || $this->type == Element::Group
            || $this->type == Element::Image
            || $this->type == Element::Italic
            || $this->type == Element::Link
            || $this->type == Element::LineBreak
            || $this->type == Element::ListItem
            || $this->type == Element::ReferenceLink
            || $this->type == Element::Reference
            || $this->type == Element::Strike
            || $this->type == Element::Strong
            || $this->type == Element::Text
            || $this->type == Element::Underlined;
    }

    public function addChild(Element $child) : void
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    public function shiftChild(Element $child) : void
    {
        $child->parent = $this;
        array_unshift($this->children, $child);
    }

    public function takeChildren(Element $element) : void
    {
        while (count($element->children) > 0)
        {
            $child = array_shift($element->children);
            $this->addChild($child);
        }
    }
}