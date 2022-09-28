#!/usr/bin/env bash

##
## Import .sql file to MariaDB container. Reverse of mysqldump.
##

# TODO:
# - kovakoodattu kontin nimi :(
# - varmista, että kontti on käynnissä -> ystävällinen virheviesti?

# abort on all errors
set -o errexit
# return exit code of last failed command
set -o pipefail
# fail on unset env variable
set -o nounset

# Projektin juuri vaikka skriptiä kutsutaisiin muusta hakemistosta
_DIR=$( cd "$(dirname $0)" ; pwd -P )

set -a # automatically export all variables
source "${_DIR}/../.env"
set +a

if [ ! -f "$1" ]; then
  echo "SQL dump file required as first parameter"
  exit 1
fi

INPUT_SQL=${1}

cat ${INPUT_SQL} | docker compose exec -T db mysql -u ${DB_USER} --password="${DB_PASSWORD}" ${DB_NAME}

