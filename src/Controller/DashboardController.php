<?php

namespace App\Controller;

use App\Repository\WebhookEntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private WebhookEntityRepository $repository)
    {
    }

    #[Route(path: '/', name: 'dashboard_homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->render(
            view: 'dashboard/homepage.html.twig',
            parameters: [
                'webhooks' => $this->repository->findBy(
                    criteria: [],
                    orderBy: ['received_at' => 'DESC'],
                    limit: 40,
                ),
            ],
        );
    }
}
