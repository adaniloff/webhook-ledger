<?php

namespace App\Infrastructure\Doctrine;

use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use Doctrine\Common\Collections\Criteria;

final readonly class WebhookCriteria
{
    public function __construct(
        public ?SourceEnum $source = null,
        public ?StatusEnum $status = null,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
    }

    public function build(bool $paginated): Criteria
    {
        $criteria = Criteria::create();

        if (null !== $this->source) {
            $criteria->andWhere(Criteria::expr()->eq('source', $this->source));
        }

        if (null !== $this->status) {
            $criteria->andWhere(Criteria::expr()->eq('status', $this->status));
        }

        if (true === $paginated && null !== $this->page && null !== $this->limit) {
            $criteria->setFirstResult(($this->page - 1) * $this->limit);
            $criteria->setMaxResults($this->limit);
        }

        return $criteria;
    }
}
