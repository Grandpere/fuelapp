# Fuelapp (Symfony + FrankenPHP)

## Notes FrankenPHP worker (Symfony 8)
- Symfony 8 uses the standard Symfony Runtime. You **do not** need `runtime/frankenphp-symfony`, which is not compatible with Symfony 8.
- The worker mode is enabled by `FRANKENPHP_CONFIG="worker ./public/index.php"` in the container.
- Do **not** set `APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime` for Symfony 8.

## Dev stack
- App: `http://localhost:${APP_PORT:-8081}`
- RabbitMQ UI: `http://localhost:${RABBITMQ_MANAGEMENT_PORT:-15673}`
- Mercure: `http://localhost:${MERCURE_PORT:-3001}`

## Run
```bash
docker compose -f resources/docker/docker-compose.yml --env-file resources/docker/.env up -d --build
```

## Docker env
The Compose env file lives at `resources/docker/.env` (example at `resources/docker/.env.example`).

## Prod (future)
This project currently targets a dev-first Docker setup. A dedicated prod profile can be added later if needed.
