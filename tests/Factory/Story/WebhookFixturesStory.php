<?php

namespace App\Tests\Factory\Story;

use App\Enum\SourceEnum;
use App\Enum\StatusEnum;
use App\Tests\Factory\WebhookEntityFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Jeu de webhooks couvrant les statuts et sources du ledger, pour peupler
 * le back-office et tester la commande `app:webhook:replay` sans attendre
 * un vrai cycle de retry Messenger.
 *
 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#stories
 */
#[AsFixture(name: 'webhook_fixtures')]
final class WebhookFixturesStory extends Story
{
    public function build(): void
    {
        // reçu, pas encore repris par un worker
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-received-0001',
            'payload' => json_encode(['action' => 'opened', 'ref' => 'refs/heads/main']),
            'headers' => $this->githubHeaders('push', 'delivery-received-0001'),
            'signature_valid' => true,
            'status' => StatusEnum::RECEIVED,
            'attempts' => 1,
            'last_error' => null,
            'version' => 1,
        ]);

        // en cours de traitement par un worker
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-dispatched-0002',
            'payload' => json_encode(['action' => 'push', 'ref' => 'refs/heads/main']),
            'headers' => $this->githubHeaders('push', 'delivery-dispatched-0002'),
            'signature_valid' => true,
            'status' => StatusEnum::DISPATCHED,
            'attempts' => 2,
            'last_error' => null,
            'version' => 1,
        ]);

        // traité avec succès
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-succeeded-0003',
            'payload' => json_encode(['action' => 'opened', 'issue' => ['number' => 42]]),
            'headers' => $this->githubHeaders('issues', 'delivery-succeeded-0003'),
            'signature_valid' => true,
            'status' => StatusEnum::SUCCEEDED,
            'attempts' => 1,
            'last_error' => null,
            'version' => 1,
        ]);

        // échoué, mais le retry_strategy Messenger va le reprendre automatiquement
        // -> volontairement NON rejouable (cf. discussion PLAN.md jour 4)
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-failed-0004',
            'payload' => json_encode(['action' => 'push', 'ref' => 'refs/heads/main']),
            'headers' => $this->githubHeaders('push', 'delivery-failed-0004'),
            'signature_valid' => true,
            'status' => StatusEnum::FAILED,
            'attempts' => 3,
            'last_error' => 'RuntimeException: Simulated handler failure in WebhookHandler::github() (attempt 3/5)',
            'version' => 1,
        ]);

        // retries épuisés, envoyé en DLQ -> celui-ci est rejouable
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-dead-0005',
            'payload' => json_encode(['action' => 'opened', 'issue' => ['number' => 7]]),
            'headers' => $this->githubHeaders('issues', 'delivery-dead-0005'),
            'signature_valid' => true,
            'status' => StatusEnum::DEAD,
            'attempts' => 6,
            'last_error' => 'RuntimeException: Simulated handler failure in WebhookHandler::github() (attempt 6/5, retries exhausted)',
            'version' => 1,
        ]);

        // signature invalide, persisté quand même (jour 2 : "les tentatives d'intrusion sont de l'information")
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::GITHUB,
            'external_event_id' => 'delivery-badsig-0006',
            'payload' => json_encode(['action' => 'push', 'ref' => 'refs/heads/main']),
            'headers' => $this->githubHeaders('push', 'delivery-badsig-0006', validSignature: false),
            'signature_valid' => false,
            'status' => StatusEnum::RECEIVED,
            'attempts' => 1,
            'last_error' => null,
            'version' => 1,
        ]);

        // Stripe : supporté par l'architecture (SourceEnum, WebhookSigner) mais non branché
        WebhookEntityFactory::createOne([
            'source' => SourceEnum::STRIPE,
            'external_event_id' => 'evt_1PfixtureStripe0007',
            'payload' => json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_fixture0007']]]),
            'headers' => [
                'content-type' => ['application/json'],
                'stripe-signature' => ['t=1730000000,v1=fixturehmac'],
            ],
            'signature_valid' => true,
            'status' => StatusEnum::RECEIVED,
            'attempts' => 1,
            'last_error' => null,
            'version' => 1,
        ]);

        WebhookEntityFactory::createMany(50);
    }

    /**
     * @return array<string, list<string>>
     */
    private function githubHeaders(string $event, string $delivery, bool $validSignature = true): array
    {
        return [
            'content-type' => ['application/json'],
            'x-github-event' => [$event],
            'x-github-delivery' => [$delivery],
            'x-hub-signature-256' => [$validSignature ? 'sha256=fixturehmac' : 'sha256=deadbeef'],
        ];
    }
}
