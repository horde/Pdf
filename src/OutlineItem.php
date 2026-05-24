<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class OutlineItem
{
    /** @var array<self> */
    private array $children = [];

    public function __construct(
        public readonly string $title,
        public readonly ?Destination $destination = null,
        public readonly bool $open = false,
    ) {}

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    /**
     * @return array<self>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function descendantCount(): int
    {
        $count = count($this->children);
        foreach ($this->children as $child) {
            $count += $child->descendantCount();
        }
        return $count;
    }
}
