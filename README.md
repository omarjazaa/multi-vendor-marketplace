# Multi-Vendor Marketplace

[![CI](https://github.com/omarjazaa/multi-vendor-marketplace/actions/workflows/ci.yml/badge.svg)](https://github.com/omarjazaa/multi-vendor-marketplace/actions/workflows/ci.yml)
![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php)
![Laravel Framework](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel)
![Tests](https://img.shields.io/badge/Tests-339%20Passing-brightgreen?logo=checkmarx)
![License](https://img.shields.io/badge/License-MIT-blue)

A scalable, multi-vendor e-commerce platform built with **Laravel 11**, designed around **Domain-Driven Design**, **SOLID principles**, and classic **GoF Design Patterns**.

---

## 🏗️ Architecture & Design Patterns

| Pattern | Module / Implementation | Purpose |
|---|---|---|
| **Decorator Pattern** | Pricing Engine (`PriceComponentInterface`, `BasePrice`, `DiscountDecorator`, `CouponDecorator`, `TaxDecorator`) | Transparent and modular price calculations for cart items and checkout totals. |
| **Chain of Responsibility** | Checkout Validation (`StockAvailabilityCheck` → `CouponValidityCheck` → `FraudRiskCheck`) | Pluggable, sequentially evaluated validation guards that can halt checkout with descriptive errors. |
| **Strategy + Factory** | Payment Processing (`PaymentGatewayInterface`, `PaymentGatewayFactory`, `StripeGateway`, `PayPalGateway`, `CashOnDeliveryGateway`) | Decoupled payment gateway integration with runtime driver resolution. |
| **Interface Segregation (ISP)** | Polymorphic Refunds (`RefundablePaymentInterface`, `PaymentGatewayInterface`, `DisputeRefundService`) | Decoupled refund contract implemented polymorphically across card, cash on delivery, and bank transfer strategies. |
| **State Pattern** | Order Lifecycle (`OrderState`, `PendingState`, `PaidState`, `ShippedState`, `DeliveredState`, `CancelledState`, `RefundedState`) | Strictly enforced, guarded state transitions across order fulfillment. |
| **Observer Pattern** | Order Automation (`OrderPlaced`, `OrderPaid`, `OrderShipped`, `OrderRefunded` with dedicated listeners) | Dispatches side-effects (inventory updates, notifications) decoupled from domain models. |
| **CQRS Split** | Vendor Analytics (`VendorDashboardQuery`, `VendorBestSellersQuery`, `VendorRevenueOverTimeQuery`) | Dedicated read-only query abstractions separated from write services. |
| **Cache Aside & Invalidation** | Catalog & Categories (`CategoryController`, `ProductCatalogController`) | High-performance Redis caching layer with tag/key invalidation on catalog mutations. |
| **Queue Worker & Jobs** | Asynchronous Background Processing (`GenerateVendorReportJob`, queued notifications) | Dedicated queues (`reports`, `notifications`, `default`) managed via Supervisor. |
| **Pipeline & Gateway** | Versioning & Rate Limiting (`ApiVersioningMiddleware`, `RateLimiter`, API Resources) | Standardized `/api/v1` scoping, correlation IDs (`X-Request-Id`), and fine-grained endpoint throttling. |

---

## 🖥️ Interactive Platform Explorer (Frontend UI)

The project includes an **interactive single-page explorer** accessible directly at `http://127.0.0.1:8000/`. It allows you to explore and test all marketplace domain features and API endpoints visually without configuring Postman or third-party tools:

1. **One-Click Persona Switcher**:
   - 👤 **Guest**: Explore public catalog, search, category filters, and product details.
   - 🛍️ **Customer (Sarah Jenkins)**: Add items to cart, test coupon codes (`WELCOME10`, `SUPER50`), checkout, review order history, and submit dispute requests.
   - 🏪 **Vendor (Apex Tech Store)**: Manage store inventory, view revenue metrics, and fulfill/ship orders with deterministic carrier tracking numbers.
   - 👑 **Admin (Omar Al-Jazaa)**: Review customer disputes, inspect original payment methods, and trigger **Polymorphic Gateway Refunds** or administrative order overrides.

2. **Real-time API Telemetry Drawer**:
   - Inspect every underlying Sanctum API request with headers, latency benchmarks, HTTP status codes, and JSON response envelopes.

3. **Quick Local Demo**:
   ```bash
   php artisan db:seed --class=MarketplaceSeeder
   php artisan serve
   # Navigate to http://127.0.0.1:8000/
   ```

| Persona | Email | Password | Role |
|---|---|---|---|
| **Admin** | `admin@marketplace.test` | `password` | Super Admin |
| **Customer** | `sarah.customer@example.com` | `password` | Verified Customer |
| **Vendor** | `vendor.apex@example.com` | `password` | Approved Merchant |

---

### Week 1 — Core Foundation & Vendor Onboarding
- [x] **Day 1**: CI/CD pipeline setup (GitHub Actions, PHPUnit, Laravel Pint).
- [x] **Day 2**: Authentication & Sanctum tokens (Customer, Vendor, Admin roles).
- [x] **Day 3**: Role-based access control & authorization gates/policies.
- [x] **Day 4**: Vendor store applications & onboarding workflow.
- [x] **Day 5**: Admin vendor approval/rejection review workflow.
- [x] **Day 6**: Nested categories & product skeleton.
- [x] **Day 7**: Vendor product CRUD with ownership protection policies.
- [x] **Day 8**: Product image uploads & API resource responses.

### Week 2 — Catalog, Cart & Pricing Engine
- [x] **Day 9**: Inventory management & low-stock alerts.
- [x] **Day 10**: Public product catalog filtering & specification query builder.
- [x] **Day 11**: Customer shopping cart with live stock validation.
- [x] **Day 12**: Discount coupons (fixed & percentage, expiry, usage limits).
- [x] **Day 13**: **Decorator Pattern**: Dynamic pricing engine calculation.
- [x] **Day 14**: Cart summary endpoint wiring & pricing edge-case tests.

### Week 3 — Orders, Payments & Automation
- [x] **Day 15**: Multi-vendor checkout & automatic vendor order splitting.
- [x] **Day 16**: **Chain of Responsibility**: Checkout validation pipeline.
- [x] **Day 17**: **Strategy + Factory**: Polymorphic payment gateways.
- [x] **Day 18**: Order notifications with multi-channel delivery strategies.
- [x] **Day 19**: **State Pattern**: Guarded order lifecycle state machine.
- [x] **Day 20**: **Observer Pattern**: Event-driven order automation.
- [x] **Day 21**: Mock shipment module with deterministic tracking numbers.

### Week 4 — Scale, Quality & Performance
- [x] **Day 22**: Product reviews & ratings cached on delivered orders.
- [x] **Day 23**: Vendor analytics dashboard with lightweight **CQRS** query split.
- [x] **Day 24**: Redis caching layer for catalog listings with invalidation hooks.
- [x] **Day 25**: **Background Jobs & Queues**: Asynchronous vendor reports, queued notifications, and Docker Supervisor worker configuration.
- [x] **Day 26**: **API Versioning (`/api/v1/...`)**, consistent resources (`UserResource`, `StoreResource`, `CategoryResource`, `VendorReportResource`), and rate limiting.
- [x] **Day 27**: **Admin Dispute/Refund Handling**: Polymorphic gateway refunds (`RefundablePaymentInterface`, `PaymentGatewayInterface`), forced `RefundedState` transitions, and dispute resolution workflows.
- [x] **Day 28**: Comprehensive testing sprint & coverage audit.
- [x] **Day 29**: **Multi-Stage Docker Production Deployment & Telemetry**: 5-stage Dockerfile, production Compose topology, live system health check API (`/api/v1/system/health`), and automated CI/CD Buildx verification.
- [ ] **Day 30**: Full architectural documentation (`ARCHITECTURE.md`) & Postman collection.

---

## ⚡ Day 29 Highlights: Multi-Stage Docker Production Deployment & System Health Telemetry

1. **5-Stage Docker Architecture & Production Orchestration**:
   - Multi-stage build isolating Node Vite compilation, Composer `--no-dev` dependency installation, base PHP 8.3 FPM runtime, immutable production images, and local development environments.
   - Comprehensive multi-container topology (`docker-compose.yml` & `docker-compose.prod.yml`) orchestrating `app`, `queue` worker, `scheduler` daemon, `mysql:8.4` (with `db` alias), `redis:7-alpine`, and `nginx:1.27-alpine` reverse proxy.
   - Complete architectural breakdown documented in [`docs/DAY_29_DOCKER_PRODUCTION_DEPLOYMENT.md`](docs/DAY_29_DOCKER_PRODUCTION_DEPLOYMENT.md).

2. **System Health & Service Telemetry Endpoint (`GET /api/v1/system/health`)**:
   - Real-time diagnostic endpoint auditing MySQL database connectivity (with latency in ms), cache subsystem operations, filesystem permissions (`storage/` & `storage/logs/`), PHP runtime, and environment status.
   - Fully backward-compatible fallback at `/api/system/health` with `X-API-Version` and UUID `X-Request-Id` tracking headers.
   - Integrated live in the Interactive Platform Explorer with one-click diagnostic telemetry.

3. **Enhanced GitHub Actions CI/CD Pipeline**:
   - Triple-stage automated workflow: PSR-12 code style audits (`Laravel Pint`), full automated test suite (339 tests, 2,049 assertions), and Docker Buildx multi-stage verification.

---

## ⚡ Day 28 Highlights: Comprehensive Testing Sprint & Critical-Path Coverage Audit

1. **337 Automated Tests & 2,021 Assertions (100% Pass Rate)**:
   - Added 32 exhaustive Feature and Unit test cases (+257 assertions) auditing critical paths, edge cases, and design pattern invariants across the codebase.
   - Full testing sprint breakdown and methodology documented in [`docs/DAY_28_TESTING_SPRINT.md`](docs/DAY_28_TESTING_SPRINT.md).
   - *Collaborative Isolation*: All authentication modules were preserved untouched to allow concurrent, conflict-free collaborative work.

2. **Multi-Vendor Checkout & Inventory Concurrency (`Day28CheckoutEdgeCasesTest`)**:
   - Validated largest-remainder proportional discount allocations across >=3 distinct vendor stores without rounding drift.
   - Boundary zero-inventory depletion and stock reservation atomicity.
   - Transactional rollback safety: if any item in a multi-vendor cart fails stock reservation, zero orders are persisted and cart lines remain intact.

3. **Guarded State Pattern & Permutation Matrix (`Day28OrderStateTransitionsTest`)**:
   - Validated the complete linear lifecycle: `pending` -> `paid` -> `shipped` -> `delivered`.
   - Enforced strict terminal immutability on `cancelled` and `refunded` orders.
   - Rejected every illegal transition jump with descriptive `409 Conflict` envelopes.

4. **Polymorphic Gateway Architecture & OCP Extensibility (`Day28PaymentGatewayAuditTest`)**:
   - Validated Interface Segregation Principle compliance across `CardPaymentStrategy`, `CashOnDeliveryStrategy`, and `BankTransferStrategy`.
   - Verified Open-Closed Principle compliance through runtime registration of custom payment gateways without core class modification.

5. **Customer Dispute Lifecycles & Admin Triage (`Day28DisputeWorkflowAuditTest`)**:
   - Validated administrative dispute rejection without order mutation, double-resolution guards, and multi-tenant customer dispute isolation.

6. **Queues, Rate Limiting & CQRS Analytics (`Day28QueuesAndAnalyticsAuditTest`)**:
   - Audited explicit background worker queue routing (`reports`, `notifications`).
   - Verified vendor data isolation on CQRS read-only queries and rate limiting enforcement.

---

## ⚡ Day 27 Highlights: Admin Dispute/Refund Handling & Polymorphic Gateway Refunds

1. **Polymorphic Gateway Refunds (`RefundablePaymentInterface` & `PaymentGatewayInterface`)**:
   - Built on SOLID's **Interface Segregation Principle (ISP)** by segregating refund capability into `RefundablePaymentInterface` and uniting it under `PaymentGatewayInterface`.
   - Polymorphic refund behaviors for every payment strategy:
     - `CardPaymentStrategy`: Simulates processor reversal with unique reference `REF-CARD-XXXXXX` and failure simulation support (`marketplace.payments.card.simulate_refund_failure`).
     - `CashOnDeliveryStrategy`: Records courier/vendor cash reversal reference `REF-COD-XXXXXX`.
     - `BankTransferStrategy`: Submits bank reversal transaction reference `REF-BANK-XXXXXX`.

2. **Forced `RefundedState` Lifecycle Transitions**:
   - Extended `OrderTransitionService::forceApply()` allowing administrators to override the state machine matrix during dispute resolution (e.g., forcing a refund on a package confirmed lost while in `shipped` status).
   - Standard transitions still guard ordinary mutations (`pending` cannot jump directly to `refunded`, already `refunded` orders cannot be re-refunded).

3. **End-to-End Customer Dispute Lifecycle**:
   - Customers can open disputes on eligible orders (`paid`, `shipped`, `delivered`).
   - Dedicated `Dispute` model and `DisputeRepositoryInterface` tracking reasons, statuses (`pending`, `under_review`, `resolved_refunded`, `rejected`), administrative notes, and resolver timestamps.
   - Admin triage and dispute resolution endpoints:
     - `POST /api/v1/admin/orders/{order}/refund`: Direct administrative order refund.
     - `GET /api/v1/admin/disputes`: List customer disputes with status filter.
     - `POST /api/v1/admin/disputes/{dispute}/refund`: Approve dispute with automatic gateway refund.
     - `POST /api/v1/admin/disputes/{dispute}/reject`: Reject dispute with explanation.
     - `POST /api/v1/orders/{order}/disputes`: Customer files dispute.

---

## ⚡ Day 26 Highlights: API Versioning, Consistent Resources & Rate Limiting

1. **Versioned API Gateway (`/api/v1/...`)**:
   - Primary endpoints cleanly scoped under `routes/api/v1.php` and prefix `/api/v1/...`.
   - Full backward compatibility maintained for legacy clients with unversioned root `/api/...` routes.
   - Global `ApiVersioningMiddleware` enforcing version headers (`X-API-Version: v1`), client version negotiation via vendor Accept header (`application/vnd.marketplace.v1+json`), and distributed tracing with `X-Request-Id` UUIDs.

2. **Consistent API Resources**:
   - `UserResource`: Standardizes user profiles across registration and login responses.
   - `StoreResource`: Encapsulates vendor store application, approval, and rejection payloads.
   - `CategoryResource`: Formats hierarchical category trees and nested children recursively.
   - `VendorReportResource`: Unifies asynchronous report generation and status tracking envelopes.

3. **Granular Rate Limiting (Throttle Middleware)**:
   - `auth` limiter: 10 requests per minute by email/IP to protect authentication routes against brute-force attacks.
   - `checkout` limiter: 10 requests per minute per user/IP to prevent checkout duplicate submission spam.
   - `reports` limiter: 5 requests per minute per vendor to guard intensive analytics jobs against resource exhaustion.
   - Standard HTTP 429 Too Many Requests response with informative JSON payload and `Retry-After` header.

1. **Vendor Analytics Reports Generation (`GenerateVendorReportJob`)**:
   - Long-running report generation is offloaded to the `reports` queue.
   - Generates JSON analytics artifacts stored in private storage.
   - Includes retry logic (`$tries = 3`) and graceful timeout management.
   - Endpoints:
     - `POST /api/vendor/analytics/reports`: Queue new report generation (`202 Accepted`).
     - `GET /api/vendor/analytics/reports`: List reports with `status` and `type` filters.
     - `GET /api/vendor/analytics/reports/{id}`: Check report status.
     - `GET /api/vendor/analytics/reports/{id}/download`: Stream completed report.

2. **Queued Notifications**:
   - `SendVendorWelcomeNotification` & `WelcomeVendorNotification` queued on `notifications`.
   - `SendOrderNotifications`, `NotifyCustomerOnPayment`, and `NotifyCustomerOnShipment` run asynchronously.

3. **Supervisor & Worker Orchestration**:
   - Supervisor config in `docker/supervisor/supervisord.conf` orchestrating workers on `default,notifications,reports`.

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite / MySQL / PostgreSQL
- Redis (optional, recommended for cache and queues)

### Local Setup
```bash
# 1. Clone repository
git clone https://github.com/omarjazaa/multi-vendor-marketplace.git
cd multi-vendor-marketplace

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Run queue worker
php artisan queue:work --queue=default,notifications,reports

# 6. Start development server
php artisan serve
```

### Running with Docker & Supervisor
```bash
docker compose up -d --build
```

---

## 🧪 Testing & Code Quality

Run tests across the entire suite:
```bash
php artisan test
```

Run code style checks:
```bash
./vendor/bin/pint --test
```

---

## 📄 License
This project is open-sourced software licensed under the [MIT license](LICENSE).
