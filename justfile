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

php-lint *args:
    docker compose exec php ./vendor/bin/php-cs-fixer {{args}}

php-test *args:
    docker compose exec php bin/phpunit {{args}}

_list:
    @just -l

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
alias test := php-test
