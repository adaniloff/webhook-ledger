<?php

namespace App\Controller;

use App\Enum\SourceEnum;
use App\Receiver\Dto\WebhookDto;
use App\Receiver\Exception\WebhookEntryDuplicationException;
use App\Receiver\Service\Receiver;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
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
        KernelInterface $kernel,
    ): Response {
        $version = (int) $request->query->get('version', 0);
        if ($version < 1) {
            throw new InvalidArgumentException(sprintf('Invalid version number: %s', $version));
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:webhook:replay',
            'uuid' => (string) $uuid,
            'version' => $version,
        ]);

        $output = new NullOutput();
        $application->run($input, $output);

        // @todo: remove once the dashboard is a React app calling this as a JSON API
        if (str_contains((string) $request->headers->get('Accept'), 'text/html')) {
            return $this->redirectToRoute('dashboard_homepage');
        }

        return new Response(content: '', headers: ['X-Evt-Id' => $uuid], status: 202);
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
