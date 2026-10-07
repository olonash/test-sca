FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron default-mysql-client tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY docker/cron/entrypoint.sh /usr/local/bin/cron-entrypoint
RUN chmod +x /usr/local/bin/cron-entrypoint

WORKDIR /app

ENTRYPOINT ["/usr/local/bin/cron-entrypoint"]