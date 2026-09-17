<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use WebhookLedger\Application\Receiver\Exception\WebhookNotFoundException;
use WebhookLedger\Application\Receiver\Exception\WebhookOutdatedException;
use WebhookLedger\Application\Receiver\Service\Receiver;
use WebhookLedger\Domain\Exception\WebhookNotReplayableException;
use WebhookLedger\Domain\ValueObject\WebhookUuid;

final class WebhookReplayController extends AbstractController
{
    #[Route(
        path: '/webhook/replay/{uuid}',
        name: 'webhook_replay',
        methods: ['POST'],
        format: 'json',
        requirements: ['uuid' => Requirement::UUID_V7],
    )]
    public function replay(
        Uuid $uuid,
        Request $request,
        Receiver $receiver,
    ): Response {
        $version = (int) $request->query->get('version', 0);
        if ($version < 1) {
            throw new \InvalidArgumentException(sprintf('Invalid version number: %s', $version));
        }

        try {
            $receiver->replay(uuid: WebhookUuid::fromString((string) $uuid), version: $version);
        } catch (WebhookNotFoundException $e) {
            return $this->replayErrorResponse(
                request: $request,
                message: sprintf('Webhook %s not found.', $e->getIdentifier()),
                status: 404,
            );
        } catch (WebhookNotReplayableException $e) {
            return $this->replayErrorResponse(
                request: $request,
                message: sprintf('Webhook %s is not replayable.', $e->getIdentifier()),
                status: 409,
            );
        } catch (WebhookOutdatedException $e) {
            return $this->replayErrorResponse(
                request: $request,
                message: sprintf(
                    'Webhook %s version is outdated (expected %s).',
                    $e->getIdentifier(),
                    $e->getOutdatedVersion(),
                ),
                status: 409,
            );
        }

        if ($this->isHtml($request)) {
            return $this->redirectToRoute('dashboard_homepage');
        }

        return new Response(content: '', headers: ['X-Evt-Id' => $uuid], status: 202);
    }

    private function replayErrorResponse(Request $request, string $message, int $status): Response
    {
        if ($this->isHtml($request)) {
            $this->addFlash('error', $message);

            return $this->redirectToRoute('dashboard_homepage');
        }

        return $this->json(data: ['error' => $message, 'fields' => []], status: $status);
    }

    private function isHtml(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Accept'), 'text/html');
    }
}
