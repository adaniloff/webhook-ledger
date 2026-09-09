<?php

namespace App\Controller;

use App\Repository\WebhookEntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'dashboard_homepage', methods: ['GET'])]
    public function homepage(WebhookEntityRepository $repository): Response
    {
        return $this->render(
            view: 'dashboard/homepage.html.twig',
            parameters: [
                'webhooks' => $repository->findAll(),
            ],
        );
    }
}
