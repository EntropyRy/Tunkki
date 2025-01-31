# Entropy Tunkki

### initialize environment

- copy .env.example to .env and symfony/.env to /symfony/.env.dev.local and change the defaults
- `docker compose build; docker compose up -d;`

#### draw the rest of the owl

- docker compose exec fpm composer install

#### database restore from dump

- docker compose exec fpm ./bin/console doctrine:database:import dump.sql

#### database creation

- docker compose exec fpm ./bin/console doctrine:schema:update --force

### Access tunkki

- open http://localhost:9090/ in your browser

### Initial creation of new user and setting it as super admin

- docker compose exec fpm ./bin/console entropy:member --password --create-user yourEmail --super-admin

### Setting up main website

- login http://localhost:9090/login
- open http://localhost:9090/admin/dashboard
- Leftside navigation Administration -> Site -> Add new -> Fill in the Name, check "Is Default" and "Enabled", Set Host as "localhost or 127.0.0.1", Locale: "Suomi", Relative Path: "/", Enabled From: "select some past date" -> Create -> "Update and close" -> Create Snapshots -> Create

#### console commands

- docker compose exec fpm ./bin/console
