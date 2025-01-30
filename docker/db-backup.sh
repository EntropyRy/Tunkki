#!/usr/bin/env bash

# abort on all errors
set -o errexit
# return exit code of last failed command
set -o pipefail
# fail on unset env variable
set -o nounset

# Projektin juuri vaikka skriptiä kutsutaisiin muusta hakemistosta
_DIR=$( cd "$(dirname $0)" ; pwd -P )

set -a # automatically export all variables
source "./.env"
set +a

# Varmista että dump tiedoston nimeen tulee aikaleima
TIMESTAMP="${1:-$(date +%Y%m%d-%H%M%S)}"
# varmista että backup-hakemisto on olemassa
mkdir -p ${_DIR}/backup

docker compose exec db /usr/bin/mariadb-dump -u ${DB_USER} --password="${DB_PASSWORD}" ${DB_NAME} | gzip > ${_DIR}/backup/db-backup-${TIMESTAMP}.sql.gz
