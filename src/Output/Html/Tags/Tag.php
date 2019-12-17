<?php

namespace Dml\Output\Html\Tags;

class Tag
{
    /**
     * Tag name
     *
     * @var string
     */
    protected $name;

    /**
     * Tag close method
     *
     * @var bool
     */
    protected $useEndTag;

    /**
     * @var array<string, string>
     */
    public $attributes;

    /**
     * @var array<string, mixed>
     */
    public $properties;

    /**
     * @var null|\Dml\Output\Html\Tag
     */
    protected $parent;

    /**
     * @var \Dml\Output\Html\Tag[]
     */
    protected $children;

    public function __construct(string $name, bool $useEndTag = true)
    {
        $this->name = $name;
        $this->useEndTag = $useEndTag;
        $this->attributes = [];
        $this->properties = [];
        $this->children = [];
    }

    public function getChildren() : array
    {
        return $this->children;
    }

    public function addChild(Tag $child) : void
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    public function unshiftChild(Tag $child) : void
    {
        $child->parent = $this;
        array_unshift($this->children, $child);
    }

    public function insertChild(int $pos, Tag $child) : void
    {
        $child->parent = $this;
        array_splice($this->children, $pos, 0, [ $child ]);
    }

    public function innerText() : string
    {
        $output = "";

        foreach ($this->children as $child)
        {
            $output .= $child->innerText();
        }

        return $output;
    }

    public function innerXml() : string
    {
        $output = "";

        foreach ($this->children as $child)
        {
            $output .= $child->outerXml();
        }

        return $output;
    }

    public function outerXml() : string
    {
        $attrs = "";
        if ($this->attributes != null)
        {
            foreach ($this->attributes as $key => $value)
                $attrs .= " ${key}=\"${value}\"";
        }

        if ($this->useEndTag)
            return "<{$this->name}{$attrs}>" . $this->innerXml() . "</{$this->name}>";

        return "<{$this->name}{$attrs} />";
    }
}