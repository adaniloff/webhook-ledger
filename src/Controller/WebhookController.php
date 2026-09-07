<?php

namespace App\Controller;

use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Service\Receiver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class WebhookController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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

        if ($failureResponse = $this->validationFailureResponse($validator, $dto, $source)) {
            return $failureResponse;
        }

        try {
            $uuid = $receiver->capture(source: $source, dto: $dto);
        } catch (WebhookEntryDuplicationException $e) {
            $uuid = $e->getIdentifier();
            $this->logger?->debug(
                sprintf('Duplication exception: source %s with ext_id %s', $source->value, $dto->external_event_id),
            );
        }

        if (true !== $withValidSignature) {
            return $this->json(data: ['error' => 'Invalid signature.', 'fields' => []], status: 401);
        }

        return new Response(content: '', headers: ['X-Evt-Id' => $uuid], status: 202);
    }

    private function validationFailureResponse(
        ValidatorInterface $validator,
        WebhookDto $dto,
        SourceEnum $source,
    ): ?Response {
        if (count($violations = $validator->validate(value: $dto)) <= 0) {
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
