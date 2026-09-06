<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(private LoggerInterface $logger, private EntityManagerInterface $em)
    {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): Response
    {
        try {
            $isConnected = $this->em->getConnection()->isConnected();
        } catch (\Throwable $t) {
            $isConnected = false;
            $this->logger->error(sprintf('Error, the connection is closed: %s', $t->getMessage()));
        }

        if (!$isConnected) {
            return new Response(content: '', status: 503);
        }

        return new Response(content: '', status: 204);
    }
}
