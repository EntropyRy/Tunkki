#!/bin/bash
dc="/usr/bin/docker compose"

$dc pull; 
$dc build --pull; 
$dc up -d; 
$dc exec fpm composer update; 
$dc exec fpm ./bin/console importmap:update; 
$dc exec fpm ./vendor/bin/phpstan analyse src --level=5
$dc exec fpm ./vendor/bin/twig-cs-fixer fix --fix templates/;
$dc exec fpm ./vendor/bin/rector process src;
