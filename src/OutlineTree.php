<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class OutlineTree
{
    /** @var array<OutlineItem> */
    private array $items = [];

    public function add(OutlineItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return array<OutlineItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function totalCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += 1 + $item->descendantCount();
        }
        return $count;
    }
}
