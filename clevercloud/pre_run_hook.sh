#!/bin/sh
set -e

php bin/console doctrine:database:create -n --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
