# laravel-microservices

![CI](https://github.com/Shaisolaris/laravel-microservices/actions/workflows/ci.yml/badge.svg)

Laravel 11 microservices architecture with an API gateway, 4 independent services (User, Order, Inventory, Notification), RabbitMQ event-driven messaging, Docker Compose orchestration, circuit breaker pattern, distributed tracing, and per-service databases. Each service owns its data and communicates asynchronously via a shared message bus.

## Stack

- **Framework:** Laravel 11, PHP 8.2+
- **Messaging:** RabbitMQ (php-amqplib) with topic exchanges
- **HTTP:** Guzzle for inter-service calls with circuit breaker
- **Infrastructure:** Docker Compose (MySQL, Redis, RabbitMQ)
- **Gateway:** Custom API gateway with request tracing and rate limiting

## Architecture

```
┌──────────────────────────────────────────────────────┐
│                    API Gateway (:8000)                │
│  Rate limiting · Request tracing · Service routing   │
└────────┬──────────┬──────────┬──────────┬────────────┘
         │          │          │          │
    ┌────▼────┐ ┌───▼────┐ ┌──▼──────┐ ┌▼───────────┐
    │  User   │ │ Order  │ │Inventory│ │Notification│
    │ Service │ │Service │ │ Service │ │  Service   │
    │ (:8001) │ │(:8002) │ │ (:8004) │ │  (:8003)   │
    │         │ │        │ │         │ │            │
    │ users_db│ │orders_db│ │invent_db│ │  (stateless)│
    └────┬────┘ └───┬────┘ └──┬──────┘ └──────┬─────┘
         │          │          │               │
         └──────────┴──────────┴───────────────┘
                          │
                    ┌─────▼─────┐
                    │ RabbitMQ  │
                    │  Message  │
                    │   Bus     │
                    └───────────┘
```

## Services

| Service | Port | Database | Responsibilities |
|---|---|---|---|
| **Gateway** | 8000 | — | Route requests, rate limit, trace, aggregate health checks |
| **User Service** | 8001 | users_db | User CRUD, publishes `user.created` events |
| **Order Service** | 8002 | orders_db | Order lifecycle, stock verification via inventory, publishes `order.*` events |
| **Inventory Service** | 8004 | inventory_db | Product catalog, stock management, publishes `inventory.low_stock` |
| **Notification Service** | 8003 | — | Listens for events, sends emails/alerts (stateless) |

## Message Flow

```
Order placed → [order.events:order.placed]
    → Notification Service: sends confirmation email
    → Inventory Service: reserves stock

Order shipped → [order.events:order.shipped]
    → Notification Service: sends tracking email

Low stock → [inventory.events:inventory.low_stock]
    → Notification Service: sends alert to admin
```

## Shared Infrastructure

### Message Bus (`shared/Messages/MessageBus.php`)
- Wraps php-amqplib with publish/subscribe/queue methods
- Topic exchanges for flexible routing patterns
- Persistent messages with JSON serialization
- Auto-generates message IDs and timestamps
- Acknowledgment-based consumption with requeue on failure

### Circuit Breaker (`shared/Messages/CircuitBreaker.php`)
- Three states: closed (normal), open (failing), half-open (testing recovery)
- Configurable failure threshold (default: 5), recovery timeout (30s), success threshold (3)
- Redis-backed state with TTL
- Fallback support when circuit is open
- Automatic recovery testing after timeout

### Service Client (`shared/Messages/ServiceClient.php`)
- HTTP client with circuit breaker wrapping every call
- Distributed tracing via `X-Trace-Id` headers
- Request/response logging with duration
- Automatic fallback responses when services are down
- Configurable timeouts per service

## API Endpoints (Gateway)

| Method | Endpoint | Proxied To |
|---|---|---|
| GET | `/api/health` | All services (aggregated) |
| GET | `/api/users` | User Service |
| POST | `/api/users` | User Service |
| GET | `/api/users/{id}` | User Service |
| GET | `/api/orders` | Order Service |
| POST | `/api/orders` | Order Service |
| GET | `/api/orders/{id}` | Order Service |
| PUT | `/api/orders/{id}/status` | Order Service |
| GET | `/api/products` | Inventory Service |
| GET | `/api/products/{id}` | Inventory Service |

## Setup

```bash
git clone https://github.com/Shaisolaris/laravel-microservices.git
cd laravel-microservices

# Start all services
docker-compose up -d

# Run migrations per service
docker-compose exec user-service php artisan migrate
docker-compose exec order-service php artisan migrate
docker-compose exec inventory-service php artisan migrate

# Gateway is available at http://localhost:8000
# RabbitMQ management at http://localhost:15672 (guest/guest)
```

## Key Design Decisions

**Per-service databases.** Each service owns its data store. The user service cannot query the orders table directly. Cross-service data is fetched via HTTP (synchronous) or populated via events (asynchronous). This enables independent deployment, scaling, and schema evolution.

**API gateway as single entry point.** External clients only talk to the gateway. The gateway routes requests, applies rate limiting and tracing, then proxies to the appropriate service. This centralizes cross-cutting concerns and hides service topology.

**RabbitMQ topic exchanges.** Events use topic routing keys (e.g., `order.placed`, `order.shipped`) on named exchanges. Services bind queues with routing patterns, enabling flexible event subscription without producer knowledge of consumers.

**Circuit breaker for resilience.** Every inter-service HTTP call goes through a circuit breaker. If a service fails 5 times, the circuit opens and returns fallback responses for 30 seconds before testing recovery. This prevents cascade failures across services.

**Distributed tracing.** Every request through the gateway gets a `X-Trace-Id` header. This ID propagates to all downstream service calls, enabling end-to-end request tracing across the microservice mesh via logs.

**Notification service is stateless.** It doesn't own a database. It subscribes to events from other services and triggers side effects (emails, SMS, push). This makes it horizontally scalable and easy to replace.

## License

MIT
