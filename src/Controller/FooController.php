<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class FooController extends AbstractController
{
    #[Route('/foo', name: 'app_foo', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Hello from FooController',
        ]);
    }

    #[Route('/foo/{name}', name: 'app_foo_show', methods: ['GET'])]
    public function show(string $name): JsonResponse
    {
        return $this->json([
            'message' => sprintf('Hello, %s!', $name),
        ]);
    }
}
