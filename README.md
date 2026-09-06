# Webhook Ledger

A ledger to help you manage your webhooks.

## Requirements

For this to work, your stack must run under those conditions:

- PHP (>= 8.2)
- MariaDB (> 10.6)

## Stack

The stack used to test this setup (see exact versions in `docker-compose` and `Dockerfile`).

- PHP 8.2-FPM
- Symfony 7 (skeleton) + Doctrine ORM + MakerBundle + PHPUnit
- Caddy (reverse proxy / serveur web)
- MariaDB 10.11
