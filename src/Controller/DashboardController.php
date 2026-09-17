<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Pagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WebhookLedger\Application\Receiver\Service\AdapterRegistry;
use WebhookLedger\Domain\Contract\WebhookLedgerProjectionInterface;
use WebhookLedger\Domain\Enum\StatusEnum;
use WebhookLedger\Domain\ValueObject\WebhookCriteria;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly WebhookLedgerProjectionInterface $repository,
        private readonly AdapterRegistry $adapters,
    ) {
    }

    #[Route(path: '/', name: 'dashboard_homepage', methods: ['GET'])]
    public function homepage(Request $request): Response
    {
        $pagination = Pagination::fromRequest($request);
        $sources = $this->adapters->names();
        $requestedSource = strtolower($request->query->get('source') ?? '');
        $criteria = new WebhookCriteria(
            source: $source = \in_array($requestedSource, $sources, true) ? $requestedSource : null,
            status: $status = StatusEnum::tryFrom(strtolower($request->query->get('status') ?? '')),
            page: $pagination->page,
            limit: $pagination->limit,
        );

        $counts = $this->repository->countByStatus();
        $total = array_sum($counts);
        $filtered = (null === $source && null === $status)
          ? $total
          : $this->repository->countBy(criteria: $criteria);

        return $this->render(
            view: 'dashboard/homepage.html.twig',
            parameters: [
                'webhooks' => $this->repository->paginate(criteria: $criteria),
                'counts' => $counts,
                'total' => $total,
                'sources' => $sources,
                'statuses' => StatusEnum::cases(),
                'selected_source' => $source,
                'selected_status' => $status,
                'page' => $pagination->page,
                'totalPages' => $pagination->totalPages($filtered),
            ],
        );
    }
}
