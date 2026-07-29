FROM postgres:17-alpine

RUN apk add --no-cache restic

COPY docker/backup/backup-postgres.sh /usr/local/bin/backup-postgres.sh

RUN chmod +x /usr/local/bin/backup-postgres.sh
