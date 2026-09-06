<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route('/webhook/{source}', name: 'webhook_hook', methods: ['POST'])]
    public function hook(Request $request): Response
    {
        $this->logger->info(sprintf('REQUEST BODY <%s>', json_encode($request->getPayload()->all())));

        return new Response(content: false, status: 202);
    }
}
