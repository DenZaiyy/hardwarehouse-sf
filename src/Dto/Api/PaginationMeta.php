<?php

namespace App\Dto\Api;

readonly class PaginationMeta
{
    public function __construct(
        public int $total,
        public int $page,
        public int $limit,
        public int $totalPages,
        public bool $hasNext,
        public bool $hasPrev,
    ) {
    }

    public function getStart(): int
    {
        return (($this->page - 1) * $this->limit) + 1;
    }

    public function getEnd(): int
    {
        return min($this->page * $this->limit, $this->total);
    }
}
