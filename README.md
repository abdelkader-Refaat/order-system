# Order processing (Laravel 12 + RabbitMQ)

Small **asynchronous order flow** for an interview take-home: create orders via HTTP, persist them as **pending**, publish a **plain JSON message** to RabbitMQ queue `orders_queue` (not a Laravel serialized job), and run **`php artisan orders:consume`** to mark orders **processed**.

## Stack

- Laravel 12, PHP 8.2+
- **php-amqplib** (via [vladimir-yuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) dependency) for direct publish/consume
- SQLite by default (any Laravel-supported DB works)

## Architecture

| Layer                                                                          | Responsibility                                                             |
| ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| Form requests                                                                  | Validate HTTP input; expose `toDto()` / `toCreateOrderDto()` to build DTOs |
| `RegisterUserDTO`, `LoginCredentialsDTO`, `UpdateProfileDTO`, `CreateOrderDTO` | Typed boundaries into services (no raw arrays in services)                 |
| `AuthTokenResult`                                                              | Small value object: `User` + plain Sanctum token after register/login      |
| `AuthService`                                                                  | Register user, login, logout (revoke token), update profile                |
| `OrderService`                                                                 | Create pending order + `OrdersQueuePublisherInterface::publish(order id)`  |
| `RabbitMqOrdersQueuePublisher`                                                 | Publishes `{"order_id":n}` JSON to `orders_queue`                          |
| `OrderProcessingService`                                                       | Consumer logic: pending → processed (idempotent)                           |
| `orders:consume`                                                               | Long-running worker (or `--once` for a single message)                     |
| `ApiController` (abstract)                                                     | Shared JSON helpers: `successResponse`, `createdResponse`, `errorResponse`, `validationErrorResponse`, `forbiddenResponse`, `notFoundResponse`, `handleException`, `resolveResource` |
| `AuthController` / `OrderController`                                           | Extend `ApiController`; validate → DTO → service → envelope + `resolveResource()` for `UserResource` / `OrderResource` |
| `OrderResource` / `UserResource`                                               | Resolved into `data` via `resolveResource($request, …)`                      |
API resources use `JsonResource::withoutWrapping()` (see `AppServiceProvider`) so single-resource responses stay **flat** at the root (easy for mobile/SPA clients).

**Queue name:** `orders_queue` (env `RABBITMQ_QUEUE`; override worker with `php artisan orders:consume --queue=…`).

## Setup

1. **Clone & install**

    ```bash
    composer install
    cp .env.example .env
    php artisan key:generate
    ```

2. **Configure queue + RabbitMQ** in `.env`:

    ```env
    QUEUE_CONNECTION=rabbitmq
    RABBITMQ_HOST=127.0.0.1
    RABBITMQ_PORT=5672
    RABBITMQ_USER=guest
    RABBITMQ_PASSWORD=guest
    RABBITMQ_VHOST=/
    RABBITMQ_QUEUE=orders_queue
    ```

3. **Database**

    ```bash
    touch database/database.sqlite
    php artisan migrate
    ```

## Run with Docker (full stack)

The repo includes a **`Dockerfile`** (PHP 8.2 CLI + SQLite + PCNTL extensions) and **`docker-compose.yml`** with:

- **`app`** — Laravel HTTP (`php artisan serve` on port **8000**)
- **`queue-worker`** — `php artisan orders:consume` (listens on `orders_queue`)
- **`rabbitmq`** — broker + management UI

### Docker Desktop (macOS / Windows)

1. **Install and start [Docker Desktop](https://www.docker.com/products/docker-desktop/).** Wait until it says **Docker is running** (whale icon steady in the menu bar / system tray).

2. **Open a terminal in the project folder** (the directory that contains `docker-compose.yml`):

    ```bash
    cd /path/to/order-system
    ```

3. **Create `.env`** (once):

    ```bash
    cp .env.example .env
    ```

4. **Generate `APP_KEY`** (pick one):
    - If you already have PHP on the host (e.g. Herd):  
      `php artisan key:generate`
    - **Using only Docker** (no local PHP needed):

        ```bash
        docker compose run --rm --no-deps app php artisan key:generate --force
        ```

5. **Build and start all services** (API + RabbitMQ + queue worker):

    ```bash
    docker compose up --build
    ```

    Leave this terminal open; logs from `app`, `queue-worker`, and `rabbitmq` will stream here. Press `Ctrl+C` to stop.

6. **Use the API** at **http://localhost:8000** (e.g. `http://localhost:8000/api/auth/register`).  
   If port **8000** is already used (Herd, another app), use another host port:

    ```bash
    APP_PORT=8080 docker compose up --build
    ```

    Then open **http://localhost:8080**.

**Optional — run in the background** (Docker Desktop still must be running):

```bash
docker compose up --build -d
```

Stop background containers: `docker compose down`.

**RabbitMQ management UI:** [http://localhost:15672](http://localhost:15672) (default login `guest` / `guest` unless you changed `RABBITMQ_USER` / `RABBITMQ_PASSWORD` in `.env`).

---

**How it works:** Compose injects `RABBITMQ_HOST=rabbitmq` and a container-friendly SQLite path; your **`.env`** is loaded for `APP_KEY` and other secrets.

**Entrypoint** (`docker/entrypoint.sh`): installs Composer deps if `vendor/` is missing (bind-mount workflow), ensures `storage` / `database` exist, runs `php artisan migrate --force`.

## Run the application (without Docker)

Terminal 1 — HTTP API:

```bash
php artisan serve
```

Terminal 2 — **worker** (consumes JSON messages and sets orders to **processed**):

```bash
php artisan orders:consume
```

Process **one** message then exit (useful for debugging):

```bash
php artisan orders:consume --once
```

Or run API + worker together (also runs Vite + Pail):

```bash
composer run dev
```

## API

### Unified JSON envelope (`key`, `msg`, `data`)

Every **`/api/*`** response (success or error) uses the same top-level shape:

```json
{
  "key": "success",
  "msg": "Human-readable message (use APP_LOCALE=ar for Arabic — see lang/ar/messages.php)",
  "data": { }
}
```

- **`key`**: only **`success`** or **`fail`** — easy for Flutter: `if (json['key'] == 'success') … else …`
- **`msg`**: translated string (`lang/en/messages.php`, `lang/ar/messages.php`)
- **`data`**: payload (object/array), or `null` when there is nothing to return (e.g. logout). **All resource payloads live under `data`** (tokens, `user`, order fields, etc.).

Tell failure *kind* from the **HTTP status** (and optional `data`):

| Situation | HTTP | `key` | Typical `data` |
|-----------|------|--------|----------------|
| Success | 200 / 201 | `success` | Your model/resource array |
| Validation / `ValidationException` | **422** | `fail` | `{ "errors": { "field": ["…"] } }` |
| Missing/invalid token | **401** | `fail` | `null` |
| Route not found | **404** | `fail` | `null` |
| Server / generic error | **500** | `fail` | optional `error` (debug only) |

Helpers: `App\Http\Responses\ApiResponse` for controllers; `App\Support\ApiExceptionResponses` (wired in `bootstrap/app.php`) for exceptions.

### Authentication (Laravel Sanctum — Bearer token)

| Method  | Path                 | Auth   | Description                                                                                |
| ------- | -------------------- | ------ | ------------------------------------------------------------------------------------------ |
| `POST`  | `/api/auth/register` | No     | `data`: `{ token, token_type, user }`                                                      |
| `POST`  | `/api/auth/login`    | No     | `data`: `{ token, token_type, user }`                                                      |
| `POST`  | `/api/auth/logout`   | Bearer | `data`: `null`                                                                             |
| `GET`   | `/api/user`          | Bearer | `data`: user object (`UserResource`)                                                       |
| `PATCH` | `/api/user`          | Bearer | `data`: updated user (`UpdateProfileRequest` → `UpdateProfileDTO` → `AuthService`)         |

**Register**

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "password": "your-secure-password",
    "password_confirmation": "your-secure-password"
  }'
```

**Login**

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{ "email": "ada@example.com", "password": "your-secure-password" }'
```

Use the returned `token` as: `Authorization: Bearer <token>`.

**Update profile**

```bash
curl -s -X PATCH http://127.0.0.1:8000/api/user \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{ "name": "New name", "email": "new@example.com" }'
```

Send at least one of `name` or `email`. Email must stay unique across users.

### `POST /api/orders` (requires Bearer token)

Creates an order with status `pending`, sets `user_id` from the **authenticated user**, and publishes **`{"order_id": <id>}`** to `orders_queue`. The HTTP response payload is under **`data`**, built with `OrderResource`.

**Example**

```bash
TOKEN="<paste token from login or register>"

curl -s -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "customer_name": "Ada Lovelace",
    "customer_email": "ada@example.com",
    "items": [
      { "name": "Keyboard", "quantity": 1, "price": 79.99 }
    ],
    "notes": "Optional note"
  }'
```

**Body (validated)**

| Field              | Rules                                       |
| ------------------ | ------------------------------------------- |
| `customer_name`    | required, string, max 255                   |
| `customer_email`   | required, email, max 255                    |
| `items`            | required array, min 1 item                  |
| `items.*.name`     | required, string, max 255                   |
| `items.*.quantity` | required, integer, 1–99999                  |
| `items.*.price`    | required, numeric ≥ 0, max 2 decimal places |
| `notes`            | optional, string, max 5000                  |

`total_amount` is computed on the server from line items (not accepted from the client). `user_id` is **not** accepted from the client; it is taken from the token.

## Retries & consumer behaviour

- Messages are **persistent** (`delivery_mode = 2`). If handling throws, the consumer **nack + requeues** so RabbitMQ can redeliver.
- There is **no** Laravel `failed_jobs` row for order messages (they are not queue jobs). Extend `OrderProcessingService` / the command if you need dead-letter or max-retry policy.

## Troubleshooting: RabbitMQ

### `getaddrinfo for rabbitmq failed` / `nodename nor servname provided`

`.env` has **`RABBITMQ_HOST=rabbitmq`**, but you are running **`php artisan`** on your **Mac** (or any host outside Docker). The name `rabbitmq` is only a DNS name **inside** Docker Compose’s private network.

| Where PHP runs | Use |
|----------------|-----|
| **Host** (Herd, Terminal, `php artisan serve`) | `RABBITMQ_HOST=127.0.0.1` and run RabbitMQ with **5672** published (e.g. `docker compose up -d rabbitmq`) |
| **Inside** `app` / `queue-worker` container | `RABBITMQ_HOST=rabbitmq` — already set in `docker-compose.yml` `environment:` (overrides `.env` in those containers) |

**Fix:** In the `.env` you use for local/Herd development, set `RABBITMQ_HOST=127.0.0.1`. Do not use `rabbitmq` as the host unless the PHP process runs in Compose.

### `Connection refused` to `127.0.0.1:5672`

`POST /api/orders` and the publisher open TCP to **`RABBITMQ_HOST:5672`**. If nothing listens, you get `AMQPIOException` / “Connection refused”.

**Fix (pick one):**

1. **Run RabbitMQ** — e.g. `docker compose up -d rabbitmq`, then retry. Ensure **5672** is published to the host if PHP runs outside Docker.
2. **Skip publish locally** — set `ORDERS_SKIP_QUEUE_PUBLISH=true` in `.env`. Orders stay **pending** with no message (not a full demo of async).
3. **Herd + broker in Docker** — `RABBITMQ_HOST=127.0.0.1` with `5672:5672` on the RabbitMQ service.

## Tests

```bash
composer run test
# or
php artisan test
```

Tests **mock** `OrdersQueuePublisherInterface` (no real RabbitMQ). `OrderProcessingService` is covered in unit tests.

## Assumptions (for reviewers)

- **Orders are authenticated:** `POST /api/orders` requires a Sanctum Bearer token; `user_id` on the row is always the authenticated user’s id.
- **Broker contract:** publish body is JSON `{"order_id": <int>}` to queue named by `RABBITMQ_QUEUE` (default `orders_queue`).

## License

MIT (same as Laravel skeleton).
