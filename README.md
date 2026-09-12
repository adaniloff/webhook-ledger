# Webhook Ledger

A small Symfony service that receives webhooks (Stripe, GitHub), persists them **before**
acknowledging, deduplicates by `(source, external_event_id)`, dispatches processing with retry
and a dead-letter queue, and lets you replay a dead event without reprocessing it twice.

Deployed on [Clever Cloud](https://www.clever-cloud.com/) - every push to `main` triggers a
deploy.

## Live instance (demo)

- Live demo available [here](https://app-db2e6f52-cf54-424d-b954-935fb2975e52.cleverapps.io)

Since this is just a demo project, you can bypass the Basic Auth with the following credentials:

- user: `demo`
- password: `OddMeEvenYou`

## Why

Since discovering the `Dual Write` issue, I've wanted to set up a boilerplate for Symfony-like
projects. The main goal was to see which existing components I'd pick, and how I could simplify
or (eventually) rework the pattern for a Symfony application.

See [Concurrency proof](#concurrency-proof) for how that's tested, and [Decisions](#decisions)
for some explanations, issues I've encountered, trade-offs I've chosen to take.

**About AI usage**: I've had *Claude* assist me code minor chores like:
- (test) setting up the concurrency test scripts.
- (test) building a set of fixtures.
- (arch) quicken the Github/Stripe signature ascertainment.
- (prod) generating the `WebhookEntity` and `WebhookDto`.

I wanted not to overuse it, as the goal was, like I said, to explore by myself.

## The flow

```mermaid
flowchart TD
    A["Stripe / GitHub<br/>POST /webhook/{source}"] --> B{"Signature valid?<br/>(constant-time HMAC)"}
    B -->|raw body read once,<br/>persisted whatever the verdict| C["INSERT webhook_entity<br/>(DBAL, status=received)"]
    C --> D{"signature_valid?"}
    D -->|no| E["202 Accepted<br/>(not dispatched)"]
    D -->|"yes, same DB transaction (atomicity)"| F["dispatch ProcessWebhookEvent(uuid)<br/>into messenger_messages"]
    F --> E2["202 Accepted"]

    F --> G["messenger:consume workers<br/>(N processes, FOR UPDATE SKIP LOCKED<br/>under the hood)"]
    G --> H["WebhookHandler<br/>loads the row by uuid"]
    H --> I["HandlerListener<br/>updates webhook_entity.status<br/>from Messenger lifecycle events"]
    I -->|ok| S["succeeded"]
    I -->|throws, retries left| RT["failed<br/>(automatic retry, backoff + jitter)"]
    RT -.retries exhausted.-> DEAD["dead<br/>(failure_transport / DLQ)"]

    DEAD --> R["POST /webhook/{uuid}?version=n<br/>(replay, only if status=dead)"]
    R --> RC{"optimistic lock<br/>on version?"}
    RC -->|stale version| RE["rejected:<br/>WebhookOutdatedException"]
    RC -->|current version| RS["status back to received<br/>+ re-dispatched"]
```

Only the UUID travels to the queue. The database row *stays* the single source of truth.

## Requirements

- PHP >= 8.2
- MariaDB > 10.6

## Stack

- PHP 8.2-FPM
- Symfony 7.4 + Doctrine ORM/DBAL + Messenger + PHPUnit
- Caddy (reverse proxy)
- MariaDB 10.11
- Deployed on [Clever Cloud](https://www.clever-cloud.com/) (git push deploy, migrations run in `clevercloud/pre_run_hook.sh` before each restart)

## Running it locally

```bash
just install     # build + start the docker stack, install dependencies
just du          # start the stack (alias for docker-up)
just console d:m:m --no-interaction  # run migrations
just test        # PHPUnit
just stan        # PHPStan, level max
just cs          # php-cs-fixer, dry-run by default
```

`GITHUB_WEBHOOK_SECRET` and `STRIPE_WEBHOOK_SECRET` are read from the `.env.local` file.
Signing a request by hand for local testing:

```bash
BODY='{"hello":"world"}'
SIG=$(php -r 'echo hash_hmac("sha256", $argv[1], "your-local-secret");' "$BODY")
curl -X POST http://localhost:8080/webhook/github \
    -H "X-GitHub-Delivery: test-1" \
    -H "X-Hub-Signature-256: sha256=$SIG" \
    --data-raw "$BODY"
```

## Endpoints

| Method | Path                          | Description                                            |
|--------|-------------------------------|----------------------------------------------------------|
| POST   | `/webhook/{source}`                      | Receive a webhook. `source` is `stripe` or `github`.    |
| POST   | `/webhook/{uuid}?version=n`              | Replay a `dead` webhook, guarded by **optimistic locking**.  |
| GET    | `/`                                      | Dashboard: list of webhooks + replay button on dead ones.    |
| GET    | `/health`                                | 204 if the database connection is up, 503 otherwise.     |

## Decisions

- **Raw DBAL `INSERT` for the ledger write (not the ORM).** Catching
`UniqueConstraintViolationException` through Doctrine's `EntityManager` closes it, which forces
rebuilding it mid-HTTP-request just to keep going. A simple DBAL `INSERT`, catch the violation, 
respond `202` either way. Deduplication is enforced by a unique index on `(source, external_event_id)`.

- **No home-grown poller reading the ledger.** The default design is often a worker doing
`SELECT ... WHERE status='received' ... FOR UPDATE SKIP LOCKED`. What's built here: `Receiver`
writes the ledger row *and* dispatches the `ProcessWebhookEvent` message **in the same DBAL
transaction**. 

    Symfony's Doctrine Messenger transport (`messenger_messages`) plays the role of the
    outbox - it already has its own `SKIP LOCKED`-style concurrent consumption, retry strategy and
    `failure_transport`, so there was no reason to hand-roll it.

- **Retry with exponential backoff and jitter, dead-letter on exhaustion.** See `config/packages/messenger.yaml`.
Why the jitter? Without it, a burst of failures all retry in lockstep and hit the
downstream dependency at the same instant.

- **Replay is restricted to `dead`, not `failed`.** A `failed` webhook already has an automatic
retry scheduled by Symfony's Messenger component. Only `dead` - retries exhausted - is safe to replay.

- **Optimistic locking on replay.** The `version` column (Doctrine `#[ORM\Version]`) guards the
replay path: two simultaneous replay clicks on the same event, one succeeds, the other gets an
`OptimisticLockException` translated into a clear rejection (`WebhookOutdatedException`) rather
than a second dispatch.

- **Constant-time signature verification, per source.** An invalid signature still gets persisted
(`signature_valid = false`) as intel (and returns `401`), but is never dispatched to a worker.

- **"Outbox" is a stretch of the term on purpose.** What's built isn't the *textbook
Transactional Outbox pattern*, however it serves (and delivers!) the same intent.

## Concurrency proof

Three scenarios, driven by shell scripts (`xargs -P`, no load-testing tool needed), run against
the live stack:

- **N parallel requests, same `external_event_id`** → exactly one row in `webhook_entity`
  (`bin/concurrency-test-dedup.sh`).
- **Two workers consuming the same queue** → no event handled twice
  (`bin/concurrency-test-workers.sh`).
- **Two simultaneous replays of the same event** → one succeeds, the other gets an
  `OptimisticLockException` (`bin/concurrency-test-replay.sh`).

Run them all with `just ccrc` (**disclosure**: *it'll reset your local database state*). 

They run in CI on every push to `main`, after the main test job.

## Known limitations

Deliberately out of scope for this iteration, not forgotten:

- No CSRF protection on the replay form -> not the point of this demo.
- Basic Auth, not per-user auth -> not the point of this demo (again).
- No payload transformation, subscription filtering, or multi-destination fan-out.
- No provider other than Stripe and GitHub.
- No multi-tenancy.
- No automatic data purge.

## Improvements I'm working on:

- Dissociating reception / worker with a better DDD approach-like.
- Reworking the frontend as a React or VueJS SPA.
- Making a Symfony bundle out of it.
