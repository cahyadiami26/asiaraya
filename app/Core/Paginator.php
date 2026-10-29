<?php
declare(strict_types=1);

namespace App\Core;

/** Very small paginator; keeps existing query string when building links. */
final class Paginator
{
    public function __construct(
        public readonly int $total,
        public readonly int $perPage = 12,
        public readonly int $page = 1,
    ) {
    }

    public static function fromRequest(int $total, int $perPage = 12): self
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $lastPage);
        return new self($total, $perPage, $page);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->lastPage();
    }

    /** Window of page numbers (max 7) around the current page. */
    public function window(int $size = 7): array
    {
        $last = $this->lastPage();
        $size = max(3, min($size, $last));
        $start = max(1, min($this->page - intdiv($size, 2), $last - $size + 1));
        return range($start, $start + $size - 1);
    }

    public function url(int $page): string
    {
        $params = $_GET;
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}
