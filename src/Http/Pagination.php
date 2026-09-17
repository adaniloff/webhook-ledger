<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final readonly class Pagination
{
    private const MAX_LIMIT = 100;

    public function __construct(
        public int $page,
        public int $limit,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: max(1, $request->query->getInt('page', 1)),
            limit: min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', 20))),
        );
    }

    public function totalPages(int $total): int
    {
        return max(1, (int) ceil($total / $this->limit));
    }
}
