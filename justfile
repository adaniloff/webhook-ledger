compose := "docker compose --env-file .env --env-file .env.local"

_default: _list

# docker - up the stack
docker-up: _ensure-ports
    {{compose}} up -d

# docker - down the stack
docker-down: _ensure-ports
    {{compose}} down --remove-orphans

# docker - build the stack
docker-build: _ensure-ports
    {{compose}} build

docker-status: _ensure-ports
    {{compose}} ps

# shell - run in the php container
php-sh:
    docker compose exec php bash

php-composer *args:
    docker compose exec php composer {{args}}

# php container - quick installation guide
php-install:
    @just du
    @just comp install

php-console *args:
    docker compose exec php bin/console {{args}}

php-cache-clear *args:
    docker compose exec php bin/console ca:cl {{args}}

php-stan *args:
    docker compose exec php ./vendor/bin/phpstan analyse src --level=max {{args}}

php-lint *args:
    docker compose exec php ./vendor/bin/php-cs-fixer {{args}}

php-test *args:
    docker compose exec php bin/phpunit {{args}}

_list:
    @just -l

# internal - test request concurrency (N requests, same event_external_id)
_test-concurrency-dedup:
    @just console d:d:d --force
    @just console d:d:c
    @just console d:m:m --no-interaction
    ./bin/concurrency-test-dedup.sh

# internal - test worker concurrency (same queue, only 1 execution)
_test-concurrency-workers:
    ./bin/concurrency-test-workers.sh

# internal - test replay concurrency (2 replays at the same time)
_test-concurrency-replay:
    ./bin/concurrency-test-replay.sh

# internal - run the whole concurrency test suite
_test-concurrency: _test-concurrency-dedup _test-concurrency-workers _test-concurrency-replay

# internal - reindex phpactor (ex: after a composer update)
_ide-reindex:
    php ~/.local/share/nvim/mason/packages/phpactor/phpactor.phar index:build --reset --working-dir=$(pwd)

# internal - assign free host ports to .env.local if not already set
_ensure-ports:
    @bash docker/ensure-ports.sh

alias du := docker-up
alias dd := docker-down
alias dps := docker-status
alias build := docker-build
alias install := php-install
alias cc := php-cache-clear
alias sh := php-sh
alias comp := php-composer
alias console := php-console
alias cs := php-lint
alias stan := php-stan
alias test := php-test
alias ide := _ide-reindex
alias ccrc := _test-concurrency
