<?php

namespace App\Controller;

use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Exception\WebhookNotFoundException;
use App\Receiver\Exception\WebhookNotReplayableException;
use App\Receiver\Exception\WebhookOutdatedException;
use App\Receiver\Service\Receiver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WebhookController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[Route(
        path: '/webhook/{uuid}',
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
            throw new InvalidArgumentException(sprintf('Invalid version number: %s', $version));
        }

        try {
            $receiver->replay(uuid: $uuid, version: $version);
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

        // @todo: remove when the frontend is a ReactJS/VueJS app
        if ($this->isHtml($request)) {
            return $this->redirectToRoute('dashboard_homepage');
        }

        return new Response(content: '', headers: ['X-Evt-Id' => $uuid], status: 202);
    }

    #[\Deprecated(message: 'to be remove after ReactJS/VueJS frontend implementation')]
    private function replayErrorResponse(Request $request, string $message, int $status): Response
    {
        if ($this->isHtml($request)) {
            $this->addFlash('error', $message);

            return $this->redirectToRoute('dashboard_homepage');
        }

        return $this->json(data: ['error' => $message, 'fields' => []], status: $status);
    }

    #[\Deprecated(message: 'to be remove after ReactJS/VueJS frontend implementation')]
    private function isHtml(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Accept'), 'text/html');
    }

    #[Route(path: '/webhook/{source}', name: 'webhook_hook', methods: ['POST'], format: 'json')]
    public function hook(
        SourceEnum $source,
        Request $request,
        Receiver $receiver,
        ValidatorInterface $validator,
    ): Response {
        $raw = $request->getContent();
        $dto = new WebhookDto(
            external_event_id: (string) $source->externalEventId($headers = $request->headers->all(), $raw),
            payload: $raw,
            headers: $headers,
            signature_valid: $withValidSignature = $receiver->sign(source: $source, headers: $headers, raw: $raw),
        );

        $this->logger?->debug(sprintf('REQUEST BODY <source: %s, payload: %s>', $source->value, $raw));
        $violations = $validator->validate(value: $dto);

        try {
            $uuid = $receiver->capture(source: $source, dto: $dto, payloadValid: 0 === count($violations));
        } catch (WebhookEntryDuplicationException $e) {
            $uuid = $e->getIdentifier();
            $this->logger?->debug(
                sprintf('Duplication exception: source %s with ext_id %s', $source->value, $dto->external_event_id),
            );
        }

        if (true !== $withValidSignature) {
            return $this->json(data: ['error' => 'Invalid signature.', 'fields' => []], status: 401);
        }

        if ($failureResponse = $this->validationFailureResponse($violations, $source)) {
            return $failureResponse;
        }

        return new Response(content: '', headers: ['X-Evt-Id' => $uuid], status: 202);
    }

    private function validationFailureResponse(
        ConstraintViolationListInterface $violations,
        SourceEnum $source,
    ): ?Response {
        if (count($violations) <= 0) {
            return null;
        }
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        $this->logger?->debug(
            sprintf('Validation error <source: %s, errors: %s>', $source->value, json_encode($errors)),
        );

        return $this->json(
            data: ['error' => 'Invalid and/or missing fields.', 'fields' => $errors],
            status: 422,
        );
    }
}
