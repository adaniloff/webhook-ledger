<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route(path: '/health', name: 'health_check', methods: ['GET'], format: 'json')]
    public function check(): Response
    {
        $throwable = null;
        try {
            $db = $this->em->getConnection()->getDatabase() ?: false;
        } catch (\Throwable $throwable) {
            $db = false;
        }

        if (!$db) {
            $this->logger?->error(sprintf('Error, the connection is closed: %s', $throwable?->getMessage()));

            return new Response(content: '', status: 503);
        }

        return new Response(content: '', status: 204);
    }
}
