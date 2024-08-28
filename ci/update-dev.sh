#!/bin/bash
dc="/usr/bin/docker compose"

$dc pull; 
$dc build --pull; 
$dc up -d; 
$dc exec fpm composer update; 
$dc exec fpm ./bin/console importmap:update; 
