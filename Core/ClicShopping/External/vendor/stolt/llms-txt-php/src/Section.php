<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use Stolt\LlmsTxt\Section\Link;

final class Section
{
    private string $name;

    private array $links = [];

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addLink(Link $link): self
    {
        $this->links[] = $link;
        return $this;
    }

    public function link(Link $link): self
    {
        return $this->addLink($link);
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function toString(): string
    {
        $section = "## " . $this->name . PHP_EOL;

        foreach ($this->links as $link) {
            $section .= PHP_EOL . "- " . $link->toString();
        }

        return $section;
    }
}
