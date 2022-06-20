# Entropy Tunkki

### dev
* copy .env.example to .env and symfony/.env to /symfony/.env.dev.local and change the defaults
* docker-compose build; docker-compose up -d;
install composer.phar in `symfony/bin` https://getcomposer.org/download/
* Example: wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | dc exec -T fpm php -- --quiet
* mv symfony/composer.phar symfony/bin/composer.phar
#### install assets
* docker-compose run --rm node yarn install
* docker-compose run --rm node yarn encore production
#### draw the rest of the owl
* docker-compose exec fpm ./bin/composer.phar install
* docker-compose exec fpm ./bin/console doctrine:database:import dump.sql
or create tables
* docker-compose exec fpm ./bin/console doctrine:schema:update --force
#### console commands
* docker-compose exec fpm ./bin/console 

