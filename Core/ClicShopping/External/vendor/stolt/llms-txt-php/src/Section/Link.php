<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt\Section;

final class Link
{
    private string $urlTitle;

    private string $url;

    private string $urlDetails = '';

    public function url(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function urlTitle(string $title): self
    {
        $this->urlTitle = $title;
        return $this;
    }

    public function getUrlTitle(): string
    {
        return $this->urlTitle;
    }

    public function urlDetails(string $details): self
    {
        $this->urlDetails = $details;
        return $this;
    }

    public function getUrlDetails(): string
    {
        return $this->urlDetails;
    }

    public function toString(): string
    {
        $link = "[%s](%s)";
        $link = \sprintf($link, $this->urlTitle, $this->url);

        if ($this->urlDetails !== '') {
            $link = $link . ": " .  $this->urlDetails;
        }

        return $link;
    }
}
