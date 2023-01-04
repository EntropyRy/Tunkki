# Entropy Tunkki

### dev
* copy .env.example to .env and symfony/.env to /symfony/.env.dev.local and change the defaults
* `docker compose build; docker compose up -d;`

#### install assets
* docker compose run --rm node yarn install
* docker compose run --rm node yarn build
#### draw the rest of the owl
* docker compose exec fpm composer install
* docker compose exec fpm ./bin/console doctrine:database:import dump.sql
or create tables
* docker compose exec fpm ./bin/console doctrine:schema:update --force
#### console commands
* docker compose exec fpm ./bin/console 

