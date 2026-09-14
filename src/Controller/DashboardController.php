<?php

namespace App\Controller;

use App\Domain\WebhookLedgerRepositoryInterface;
use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Http\Pagination;
use App\Infrastructure\Doctrine\WebhookCriteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private WebhookLedgerRepositoryInterface $repository)
    {
    }

    #[Route(path: '/', name: 'dashboard_homepage', methods: ['GET'])]
    public function homepage(Request $request): Response
    {
        $pagination = Pagination::fromRequest($request);
        $criteria = new WebhookCriteria(
            source: $source = SourceEnum::tryFrom(strtolower($request->query->get('source') ?? '')),
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
                'sources' => SourceEnum::cases(),
                'statuses' => StatusEnum::cases(),
                'selected_source' => $source,
                'selected_status' => $status,
                'page' => $pagination->page,
                'totalPages' => $pagination->totalPages($filtered),
            ],
        );
    }
}
