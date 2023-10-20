#!/bin/bash
dc="/usr/bin/docker compose"

$dc pull; 
$dc build --pull; 
$dc up -d; 
$dc exec fpm composer update; 
$dc run --rm node yarn upgrade; 
$dc run --rm node yarn install --force;
$dc run --rm node yarn build;
$dc run --rm node yarn e30v-build;
