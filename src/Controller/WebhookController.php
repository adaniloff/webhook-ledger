<?php

namespace App\Controller;

use App\Dto\WebhookDto;
use App\Enum\SourceEnum;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[Route(path: '/webhook/{source}', name: 'webhook_hook', methods: ['POST'], format: 'json')]
    public function hook(
        SourceEnum $source,
        #[MapRequestPayload(acceptFormat: 'json')] WebhookDto $dto,
    ): Response {
        $payload = serialize($dto);
        $this->logger?->debug(sprintf('REQUEST BODY <source: %s, payload: %s>', $source->value, $payload));

        return new Response(content: '', status: 202);
    }
}
