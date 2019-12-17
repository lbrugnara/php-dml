<?php

namespace Dml\Output\Html;

use Dml\Output\Html\Tags\Blockquote;
use Dml\Output\Html\Tags\Br;
use Dml\Output\Html\Tags\Code;
use Dml\Output\Html\Tags\Document;
use Dml\Output\Html\Tags\Group;
use Dml\Output\Html\Tags\Header;
use Dml\Output\Html\Tags\Hr;
use Dml\Output\Html\Tags\Image;
use Dml\Output\Html\Tags\Italic;
use Dml\Output\Html\Tags\Link;
use Dml\Output\Html\Tags\ListItem;
use Dml\Output\Html\Tags\OrderedList;
use Dml\Output\Html\Tags\Paragraph;
use Dml\Output\Html\Tags\Preformatted;
use Dml\Output\Html\Tags\ReferenceGroup;
use Dml\Output\Html\Tags\Tag;
use Dml\Output\Html\Tags\Text;
use Dml\Output\Html\Tags\UnorderedList;

class DocBuilder
{
    private $visitors;

    public function __construct()
    {
        $this->visitors = [
            \Dml\Parser\Elements\Element::Blockquote         => [ $this, 'visitBlockquote' ],
            \Dml\Parser\Elements\Element::CodeBlock          => [ $this, 'visitCodeBlock' ],
            \Dml\Parser\Elements\Element::Custom             => [ $this, 'visitCustom' ],
            \Dml\Parser\Elements\Element::InlineCode         => [ $this, 'visitInlineCode' ],
            \Dml\Parser\Elements\Element::Group              => [ $this, 'visitGroup' ],
            \Dml\Parser\Elements\Element::Header             => [ $this, 'visitHeader' ],
            \Dml\Parser\Elements\Element::ThematicBreak      => [ $this, 'visitThematicBreak' ],
            \Dml\Parser\Elements\Element::Image              => [ $this, 'visitImage' ],
            \Dml\Parser\Elements\Element::Italic             => [ $this, 'visitItalic' ],
            \Dml\Parser\Elements\Element::Link               => [ $this, 'visitLink' ],
            \Dml\Parser\Elements\Element::UnorderedList      => [ $this, 'visitUnorderedList' ],
            \Dml\Parser\Elements\Element::OrderedList        => [ $this, 'visitOrderedList' ],
            \Dml\Parser\Elements\Element::TodoList           => [ $this, 'visitTodoList' ],
            \Dml\Parser\Elements\Element::ListItem           => [ $this, 'visitListItem' ],
            \Dml\Parser\Elements\Element::LineBreak          => [ $this, 'visitLineBreak' ],
            \Dml\Parser\Elements\Element::Paragraph          => [ $this, 'visitParagraph' ],
            \Dml\Parser\Elements\Element::Preformatted       => [ $this, 'visitPreformatted' ],
            \Dml\Parser\Elements\Element::ReferenceGroup     => [ $this, 'visitReferenceGroup' ],
            \Dml\Parser\Elements\Element::ReferenceLink      => [ $this, 'visitReferenceLink' ],
            \Dml\Parser\Elements\Element::Reference          => [ $this, 'visitReference' ],
            \Dml\Parser\Elements\Element::Strike             => [ $this, 'visitStrike' ],
            \Dml\Parser\Elements\Element::Strong             => [ $this, 'visitStrong' ],
            \Dml\Parser\Elements\Element::Text               => [ $this, 'visitText' ],
            \Dml\Parser\Elements\Element::Underlined         => [ $this, 'visitUnderlined' ],
        ];
    }

    public function build(\Dml\Parser\Elements\Document $document) : Document
    {
        $html = new Document();
        $html->head = new Tag("head");
        $html->body = new Tag("body");
        
        foreach ($document->getChildren() as $dmlElement)
        {
            $this->visitElement($dmlElement, $html->body);
        }

        return $html;
    }

    private function visitElement(\Dml\Parser\Elements\Element $dmlElement, Tag $parentTag) : Tag
    {
        return $this->visitors[$dmlElement->type]($dmlElement, $parentTag);
    }

    private function visitBlockquote(\Dml\Parser\Elements\Blockquote $dmlElement, Tag $parentTag) : Tag
    {
        $blockquote = new Blockquote();

        $blockquote->attributes = \array_merge($blockquote->attributes, $dmlElement->attributes);
        $blockquote->properties = \array_merge($blockquote->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $blockquote);

        $parentTag->addChild($blockquote);
        return $blockquote;
    }

    private function visitCodeBlock(\Dml\Parser\Elements\Code $dmlElement, Tag $parentTag) : Tag
    {
        $code = new Code(true);

        $code->attributes = \array_merge($code->attributes, $dmlElement->attributes);
        $code->properties = \array_merge($code->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $code);

        $parentTag->addChild($code);
        return $code;
    }

    private function visitCustom(\Dml\Parser\Elements\Custom $dmlElement, Tag $parentTag) : Tag
    {
        $custom = new Tag($dmlElement->name);

        $custom->attributes = \array_merge($custom->attributes, $dmlElement->attributes);
        $custom->properties = \array_merge($custom->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $custom);

        $parentTag->addChild($custom);
        return $custom;
    }

    private function visitInlineCode(\Dml\Parser\Elements\Code $dmlElement, Tag $parentTag) : Tag
    {
        $blockquote = new Code(false);

        $blockquote->attributes = \array_merge($blockquote->attributes, $dmlElement->attributes);
        $blockquote->properties = \array_merge($blockquote->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $blockquote);

        $parentTag->addChild($blockquote);
        return $blockquote;
    }

    private function visitGroup(\Dml\Parser\Elements\Group $dmlElement, Tag $parentTag) : Tag
    {
        $group = new Group();

        $group->attributes = \array_merge($group->attributes, $dmlElement->attributes);
        $group->properties = \array_merge($group->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $group);

        $parentTag->addChild($group);
        return $group;
    }

    private function visitHeader(\Dml\Parser\Elements\Header $dmlElement, Tag $parentTag) : Tag
    {
        $header = new Header($dmlElement->header);

        $header->attributes = \array_merge($header->attributes, $dmlElement->attributes);
        $header->properties = \array_merge($header->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $header);

        // Build an id for the header
        $id = trim(\mb_strtolower(str_replace(" ", "-", $header->innerText())));
        //// TODO: Should we sanitize/modify something here?
        $header->attributes["id"] = $id;

        $parentTag->addChild($header);
        return $header;
    }

    private function visitThematicBreak(\Dml\Parser\Elements\ThematicBreak $dmlElement, Tag $parentTag) : Tag
    {
        $hr = new Hr();

        $hr->attributes = \array_merge($hr->attributes, $dmlElement->attributes);
        $hr->properties = \array_merge($hr->properties, $dmlElement->properties);

        $parentTag->addChild($hr);
        return $hr;
    }

    private function visitImage(\Dml\Parser\Elements\Image $dmlElement, Tag $parentTag) : Tag
    {
        $image = new Image($dmlElement->title, $dmlElement->source, $dmlElement->altTitle);

        $image->attributes = \array_merge($image->attributes, $dmlElement->attributes);
        $image->properties = \array_merge($image->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $image);

        $parentTag->addChild($image);
        return $image;
    }

    private function visitItalic(\Dml\Parser\Elements\Italic $dmlElement, Tag $parentTag) : Tag
    {
        $italic = new Italic();

        $italic->attributes = \array_merge($italic->attributes, $dmlElement->attributes);
        $italic->properties = \array_merge($italic->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $italic);

        $parentTag->addChild($italic);
        return $italic;
    }

    private function visitLink(\Dml\Parser\Elements\Link $dmlElement, Tag $parentTag) : Tag
    {
        $link = new Link($dmlElement->href, $dmlElement->title);

        $link->attributes = \array_merge($link->attributes, $dmlElement->attributes);
        $link->properties = \array_merge($link->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $link);

        $parentTag->addChild($link);
        return $link;
    }

    private function visitUnorderedList(\Dml\Parser\Elements\UnorderedList $dmlElement, Tag $parentTag) : Tag
    {
        $ul = new UnorderedList();

        $ul->attributes = \array_merge($ul->attributes, $dmlElement->attributes);
        $ul->properties = \array_merge($ul->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $ul);

        $parentTag->addChild($ul);
        return $ul;
    }

    private function visitOrderedList(\Dml\Parser\Elements\OrderedList $dmlElement, Tag $parentTag) : Tag
    {
        $ol = new OrderedList();

        $ol->attributes = \array_merge($ol->attributes, $dmlElement->attributes);
        $ol->properties = \array_merge($ol->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $ol);

        $parentTag->addChild($ol);
        return $ol;
    }

    private function visitTodoList(\Dml\Parser\Elements\TodoList $dmlElement, Tag $parentTag) : Tag
    {
        $tdl = new UnorderedList();

        $tdl->attributes = \array_merge($tdl->attributes, $dmlElement->attributes);
        $tdl->properties = \array_merge($tdl->properties, $dmlElement->properties);

        $this->attributes["class"] = "todo";

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $tdl);

        $parentTag->addChild($tdl);
        return $tdl;
    }

    private function visitListItem(\Dml\Parser\Elements\ListItem $dmlElement, Tag $parentTag) : Tag
    {
        $li = new ListItem();

        $li->attributes = \array_merge($li->attributes, $dmlElement->attributes);
        $li->properties = \array_merge($li->properties, $dmlElement->properties);

        if ($dmlElement instanceof \Dml\Parser\Elements\TodoListItem)
        {
            $input = new Tag("input", false);
            $input->attributes["type"] = "checkbox";
            $input->attributes["disabled"] = "disabled";
            $li->addChild($input);
            
            if ($dmlElement->completed)
                $li->attributes["checked"] = "checked";
        }

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $li);

        $parentTag->addChild($li);

        return $li;
    }

    private function visitLineBreak(\Dml\Parser\Elements\LineBreak $dmlElement, Tag $parentTag) : Tag
    {
        $br = new Br();

        $br->attributes = \array_merge($br->attributes, $dmlElement->attributes);
        $br->properties = \array_merge($br->properties, $dmlElement->properties);

        $parentTag->addChild($br);
        return $br;
    }

    private function visitParagraph(\Dml\Parser\Elements\Paragraph $dmlElement, Tag $parentTag) : Tag
    {
        $p = new Paragraph();

        $p->attributes = \array_merge($p->attributes, $dmlElement->attributes);
        $p->properties = \array_merge($p->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $p);

        $parentTag->addChild($p);
        return $p;
    }

    private function visitPreformatted(\Dml\Parser\Elements\Preformatted $dmlElement, Tag $parentTag) : Tag
    {
        $pre = new Preformatted();

        $pre->attributes = \array_merge($pre->attributes, $dmlElement->attributes);
        $pre->properties = \array_merge($pre->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $pre);

        $parentTag->addChild($pre);
        return $pre;
    }

    private function visitReferenceGroup(\Dml\Parser\Elements\ReferenceGroup $dmlElement, Tag $parentTag) : Tag
    {
        $refGroup = new ReferenceGroup();

        $refGroup->attributes = \array_merge($refGroup->attributes, $dmlElement->attributes);
        $refGroup->properties = \array_merge($refGroup->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $refGroup);

        $parentTag->addChild($refGroup);
        return $refGroup;
    }

    private function visitReferenceLink(\Dml\Parser\Elements\ReferenceLink $dmlElement, Tag $parentTag) : Tag
    {
        $link = new Tag("sup");
        $a = new Link("#" . $dmlElement->reference, $dmlElement->reference);
        $a->addChild(new Text(trim(isset($dmlElement->title[0]) ? $dmlElement->title : $dmlElement->reference )));
        $link->addChild($a);

        $link->attributes = \array_merge($link->attributes, $dmlElement->attributes);
        $link->properties = \array_merge($link->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $link);

        $parentTag->addChild($link);
        return $link;
    }

    private function visitReference(\Dml\Parser\Elements\Reference $dmlElement, Tag $parentTag) : Tag
    {
        $span = new Tag("span");

        $span->attributes = \array_merge($span->attributes, $dmlElement->attributes);
        $span->properties = \array_merge($span->properties, $dmlElement->properties);

        $span->attributes["id"] = $dmlElement->refid;
        
        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $span);

        $parentTag->addChild($span);
        return $span;
    }

    private function visitStrike(\Dml\Parser\Elements\Strike $dmlElement, Tag $parentTag) : Tag
    {
        $strike = new Tag("span");

        $strike->attributes = \array_merge($strike->attributes, $dmlElement->attributes);
        $strike->properties = \array_merge($strike->properties, $dmlElement->properties);

        $strike->attributes["style"] = "text-decoration: line-through";

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $strike);

        $parentTag->addChild($strike);
        return $strike;
    }

    private function visitStrong(\Dml\Parser\Elements\Strong $dmlElement, Tag $parentTag) : Tag
    {
        $strike = new Tag("strong");

        $strike->attributes = \array_merge($strike->attributes, $dmlElement->attributes);
        $strike->properties = \array_merge($strike->properties, $dmlElement->properties);

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $strike);

        $parentTag->addChild($strike);
        return $strike;
    }

    private function visitText(\Dml\Parser\Elements\Text $dmlElement, Tag $parentTag) : Tag
    {
        $text = new Text($dmlElement->content);
        $parentTag->addChild($text);
        return $text;
    }

    private function visitUnderlined(\Dml\Parser\Elements\Underline $dmlElement, Tag $parentTag) : Tag
    {
        $underline = new Tag("span");

        $underline->attributes = \array_merge($underline->attributes, $dmlElement->attributes);
        $underline->properties = \array_merge($underline->properties, $dmlElement->properties);

        $underline->attributes["style"] = "text-decoration: underline";

        foreach ($dmlElement->getChildren() as $childElement)
            $this->visitElement($childElement, $underline);

        $parentTag->addChild($underline);
        return $underline;
    }

}
