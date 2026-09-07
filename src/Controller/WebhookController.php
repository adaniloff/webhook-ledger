<?php

namespace App\Controller;

use App\Dto\WebhookDto;
use App\Enum\SourceEnum;
use App\Repository\WebhookEventRepository;
use App\Service\WebhookSigner;
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
        WebhookEventRepository $repository,
        WebhookSigner $signer,
        ValidatorInterface $validator,
    ): Response {
        $raw = $request->getContent();

        $dto = new WebhookDto(
            external_event_id: (string) $source->externalEventId($headers = $request->headers->all(), $raw),
            payload: $raw,
            headers: $headers,
            signature_valid: $signer->verify(source: $source, headers: $headers, raw: $raw),
        );

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json(
                data: ['error' => 'Invalid and/or missing fields.', 'fields' => $errors],
                status: 422,
            );
        }

        $this->logger?->debug(sprintf('REQUEST BODY <source: %s, payload: %s>', $source->value, $raw));
        $repository->receive(source: $source, dto: $dto);

        if (!$dto->signature_valid) {
            return $this->json(data: ['error' => 'Invalid signature.', 'fields' => []], status: 401);
        }

        return new Response(content: '', status: 202);
    }
}
