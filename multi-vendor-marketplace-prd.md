# Product Requirements Document (PRD)
## Multi-Vendor E-Commerce Marketplace — Backend API (Laravel)

**Document owner:** Omar
**Purpose of this document:** This PRD is written to be handed to an AI coding assistant (or a human developer) as the single source of truth to build this project incrementally, feature by feature, over 30 days. Each section is scoped so it can be implemented, tested, and committed independently.

---

## 1. Project Overview

### 1.1 Summary
A backend-only RESTful API for a **multi-vendor e-commerce marketplace**, similar in spirit to Etsy, Noon, or Amazon Marketplace. Multiple independent vendors (merchants) can register, open their own stores, list products, and manage their own orders — all on a single shared platform operated by a platform admin.

### 1.2 Goals
- Build a production-quality Laravel backend that demonstrates strong software engineering fundamentals: SOLID principles, clean architecture, and well-known design patterns applied naturally (not forced).
- Ship incrementally: one meaningful, demo-able feature per day for 30 days, each committed to GitHub with a clear message.
- End with a portfolio-grade project: documented, tested, containerized, and CI-enabled.

### 1.3 Non-Goals (out of scope for this PRD)
- No frontend/UI (API-only; Postman/Swagger used for demonstration).
- No real payment processing — payment gateways will be integrated in **sandbox/test mode** only.
- No real shipping carrier integration — shipping will be simulated with a mock provider.
- No mobile app.

### 1.4 Target Users (of the platform being built)
- **Customer**: browses products from multiple vendors, buys, tracks orders, reviews products.
- **Vendor (Merchant)**: manages their own store, products, inventory, and orders.
- **Admin**: oversees the platform — approves vendors, manages disputes, views global analytics.

---

## 2. Tech Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 11.x |
| Database | MySQL 8 (or PostgreSQL) |
| Auth | Laravel Sanctum (token-based API auth) |
| Authorization | Laravel Policies + Gates |
| Queue | Laravel Queues (database or Redis driver) |
| Cache | Redis (or file/database cache for local dev) |
| Testing | PHPUnit / Pest, Feature + Unit tests |
| Containerization | Docker + Docker Compose |
| CI/CD | GitHub Actions |
| API Docs | Postman Collection (+ optional Scribe/L5-Swagger) |
| Payment (sandbox) | Stripe Test Mode, PayPal Sandbox, Cash on Delivery (mock) |

---

## 3. Core Architectural Principles (must be visibly applied)

This section is the "constitution" the AI assistant should keep re-reading before writing code for any feature.

### 3.1 SOLID
- **S — Single Responsibility**: Controllers stay thin (HTTP concerns only). Business logic lives in **Service classes**. Data access logic lives in **Repository classes**. Validation lives in **Form Request classes**.
- **O — Open/Closed**: New payment methods, notification channels, or discount types should be addable by creating a new class that implements an existing interface — never by editing existing `if/else` or `switch` blocks.
- **L — Liskov Substitution**: Any class implementing `PaymentGatewayInterface`, `DiscountInterface`, `NotificationChannelInterface`, etc., must be fully substitutable without breaking the caller.
- **I — Interface Segregation**: Prefer several small, focused interfaces (e.g., `Refundable`, `Capturable`) over one large `PaymentGatewayInterface` that forces unrelated methods on every implementation.
- **D — Dependency Inversion**: High-level services depend on interfaces (bound in a Service Provider), not on concrete classes. Use Laravel's IoC container / constructor injection everywhere — avoid `new SomeClass()` inside business logic.

### 3.2 Design Patterns Map (feature → pattern)

| Pattern | Where it's used |
|---|---|
| Repository Pattern | Data access for Stores, Products, Orders |
| Service Layer | All business logic (OrderService, PricingService, VendorService…) |
| Strategy Pattern | Payment gateways, Notification channels |
| Factory Pattern | Creating the right Payment/Notification strategy at runtime |
| Decorator Pattern | Price calculation pipeline (base price → discount → coupon → tax → shipping) |
| State Pattern | Order lifecycle (Pending → Paid → Shipped → Delivered → Refunded/Cancelled) |
| Chain of Responsibility | Order validation pipeline before checkout (stock check → payment check → fraud check) |
| Observer Pattern | Laravel Events/Listeners (OrderPlaced → notify vendor, reduce stock, send email) |
| Specification Pattern (optional, Week 3+) | Complex product filtering/search queries |
| CQRS (lightweight) | Separating vendor analytics read queries from the write side |

### 3.3 Layered Architecture
```
Client (Postman/Frontend)
   ↓
Routes (api.php)
   ↓
Controllers (thin — validate input via Form Requests, call a Service, return a Resource)
   ↓
Services (business logic, orchestration, fires Events)
   ↓
Repositories (interface + Eloquent implementation — data access only)
   ↓
Models (Eloquent, relationships, minimal logic)
```

### 3.4 Coding Standards
- PSR-12 coding style.
- Every public class method has a docblock explaining intent (not restating the code).
- Every feature branch = 1 GitHub Issue + 1 Pull Request merged into `main`, even working solo — this builds the habit of professional git workflow.
- Every day's commit message follows: `feat(day-XX): short description` or `fix(day-XX): ...`.

---

## 4. Domain Model (Core Entities)

- **User** (base auth model) — has role: `customer`, `vendor`, `admin`
- **VendorProfile** — 1:1 with User (vendor-specific data: store name, description, verification status)
- **Store** — belongs to VendorProfile
- **Category** — hierarchical (self-referencing parent_id)
- **Product** — belongs to Store, belongs to Category, has many ProductImages, has many ProductVariants (optional stretch)
- **Inventory** — tracks stock per product (or per variant)
- **Cart** / **CartItem** — belongs to User
- **Coupon** — code, type (fixed/percentage), constraints (min order, expiry, usage limit)
- **Order** — belongs to User (customer), has many OrderItems (each OrderItem references a Store/vendor, so one Order can span multiple vendors)
- **OrderItem** — belongs to Order, belongs to Product, snapshot of price at purchase time
- **Payment** — belongs to Order, gateway used, status, transaction reference
- **Shipment** (mock) — belongs to OrderItem or Order, status
- **Review** — belongs to Product, belongs to User, rating + comment
- **Notification** — Laravel's built-in notifications table, or custom
- **AuditLog** (stretch, Week 4) — records key actions for traceability

---

## 5. 30-Day Feature Roadmap (Daily Granularity)

> Instruction to the AI assistant: implement **one numbered item per day**. Each day should end in a working, testable increment with at least one feature test, then a git commit and push.

### Week 1 — Foundation & Vendor Isolation
1. **Day 1**: Project setup — Laravel install, Docker Compose (app, MySQL, Redis), `.env` config, base folder structure for `app/Services`, `app/Repositories`, `app/Repositories/Contracts`.
2. **Day 2**: User model with `role` enum, Sanctum auth (register/login/logout), base Form Requests, base `ApiResponse` helper/trait for consistent JSON responses.
3. **Day 3**: Roles & Policies — Gate definitions for `customer`, `vendor`, `admin`; middleware to protect vendor-only and admin-only routes.
4. **Day 4**: VendorProfile & Store — vendor can apply to open a store (status: pending/approved/rejected); Repository Pattern introduced here (`StoreRepositoryInterface` + `EloquentStoreRepository`).
5. **Day 5**: Admin vendor approval flow — admin approves/rejects a vendor application; fires `VendorApproved` event (first use of Observer pattern, e.g., sends a welcome notification).
6. **Day 6**: Category management (hierarchical, admin-only CRUD) + Product model skeleton (belongs to Store & Category).
7. **Day 7**: Product CRUD for vendors via `ProductService` + `ProductRepositoryInterface`; policy ensures a vendor can only edit their own products. Write first batch of Feature tests (auth + store + product).

### Week 2 — Catalog & Pricing Engine
8. **Day 8**: Product images (multiple per product, upload handling), API Resources for clean JSON output.
9. **Day 9**: Inventory management — stock tracking, low-stock flag, vendor stock update endpoint.
10. **Day 10**: Product search & filtering (by category, price range, store, rating) — introduce a simple `ProductFilter`/Specification-style query builder.
11. **Day 11**: Cart module — add/update/remove items, cart belongs to authenticated customer.
12. **Day 12**: Coupon model + admin/vendor coupon creation (fixed/percentage, expiry, usage limits).
13. **Day 13**: **Pricing Engine via Decorator Pattern** — `PriceComponentInterface` with `BasePrice`, `DiscountDecorator`, `CouponDecorator`, `TaxDecorator` — each wraps the previous to compute a final price transparently.
14. **Day 14**: Wire the pricing engine into the Cart summary endpoint (show subtotal, discount, tax, total) + tests for pricing edge cases.

### Week 3 — Orders, Payments & Automation
15. **Day 15**: Order creation from Cart — `OrderService::checkout()`, splits a single cart into per-vendor Orders/OrderItems (multi-vendor checkout).
16. **Day 16**: **Chain of Responsibility** — checkout validation pipeline: `StockAvailabilityCheck` → `CouponValidityCheck` → `FraudRiskCheck` (simple heuristic), each handler can halt the chain with a clear error.
17. **Day 17**: **Strategy + Factory for Payments** — `PaymentGatewayInterface` with `StripeGateway` (test mode), `PayPalGateway` (sandbox), `CashOnDeliveryGateway`; `PaymentGatewayFactory` picks the right one at checkout.
18. **Day 18**: Payment webhook handling (Stripe test webhook) to confirm payment asynchronously; update Order/Payment status.
19. **Day 19**: **State Pattern for Order Lifecycle** — `OrderState` interface with `PendingState`, `PaidState`, `ShippedState`, `DeliveredState`, `RefundedState`, `CancelledState`, each defining legal transitions.
20. **Day 20**: **Observer Pattern** — `OrderPlaced`, `OrderPaid`, `OrderShipped` events with Listeners: reduce inventory, notify vendor, notify customer (queued listeners).
21. **Day 21**: Mock Shipment module — vendor marks an OrderItem as shipped, generates a fake tracking number, fires `OrderShipped`.

### Week 4 — Scale, Quality & Professionalism
22. **Day 22**: Product Reviews & Ratings — customers can review only products from Delivered orders; average rating cached on Product.
23. **Day 23**: Vendor Analytics Dashboard endpoints — total sales, best-sellers, revenue over time; introduce a **lightweight CQRS split** (dedicated read-only query classes separate from the write-side Services).
24. **Day 24**: Caching layer — cache category tree and product catalog listings (Redis), cache invalidation on product/category update.
25. **Day 25**: Background Jobs & Queues — move emails, notifications, and report generation to queued jobs; configure `supervisor`/queue worker in Docker.
26. **Day 26**: API versioning (`/api/v1/...`) + consistent API Resource transformations across all endpoints; rate limiting on sensitive routes (login, checkout).
27. **Day 27**: Admin dispute/refund handling — admin can force a `RefundedState` transition, triggers refund through the original `PaymentGatewayInterface` implementation used (polymorphism in action).
28. **Day 28**: Testing sprint — raise Feature/Unit test coverage across all modules (target: all critical paths covered: auth, checkout, payment, order state transitions).
29. **Day 29**: Dockerize fully (multi-stage Dockerfile, docker-compose for app/queue/scheduler/db/redis) + GitHub Actions CI pipeline (lint + test on every push/PR).
30. **Day 30**: Full documentation — README (architecture diagram, setup instructions, pattern explanations), Postman collection export, `ARCHITECTURE.md` explaining each SOLID/pattern decision with file references.

**Days 31–32 (buffer)**: Full refactor pass — walk through the whole codebase explicitly checking each of the 5 SOLID principles; fix any controller that grew too "fat"; write a short `LESSONS.md` reflecting on trade-offs made.

---

## 6. API Endpoints (High-Level, Week 1–2 shown as example — assistant should extend this table as each day is built)

| Method | Endpoint | Role | Description |
|---|---|---|---|
| POST | /api/v1/auth/register | Public | Register as customer or vendor |
| POST | /api/v1/auth/login | Public | Login, returns Sanctum token |
| POST | /api/v1/auth/logout | Authenticated | Revoke token |
| POST | /api/v1/vendor/apply | Vendor | Apply to open a store |
| POST | /api/v1/admin/vendors/{id}/approve | Admin | Approve vendor application |
| GET/POST/PUT/DELETE | /api/v1/vendor/products | Vendor | Manage own products |
| GET | /api/v1/products | Public | Browse/search/filter products |
| POST | /api/v1/cart/items | Customer | Add product to cart |
| GET | /api/v1/cart | Customer | View cart with computed pricing |
| POST | /api/v1/checkout | Customer | Run checkout pipeline, create order(s) |
| POST | /api/v1/orders/{id}/pay | Customer | Trigger selected payment strategy |
| GET | /api/v1/orders | Customer/Vendor | List own orders |
| POST | /api/v1/vendor/orders/{item}/ship | Vendor | Mark item shipped |
| POST | /api/v1/products/{id}/reviews | Customer | Leave a review |
| GET | /api/v1/vendor/analytics | Vendor | Sales dashboard data |

---

## 7. Database Schema Notes (key tables, not exhaustive)

- `users` (id, name, email, password, role, timestamps)
- `vendor_profiles` (id, user_id, store status, verified_at)
- `stores` (id, vendor_profile_id, name, slug, description)
- `categories` (id, parent_id nullable, name, slug)
- `products` (id, store_id, category_id, name, slug, description, base_price, is_active)
- `product_images` (id, product_id, path, is_primary)
- `inventories` (id, product_id, quantity, low_stock_threshold)
- `carts` / `cart_items`
- `coupons` (id, code, type, value, expires_at, usage_limit, min_order_amount)
- `orders` (id, user_id, status, total_amount, placed_at)
- `order_items` (id, order_id, product_id, store_id, quantity, unit_price, subtotal)
- `payments` (id, order_id, gateway, status, transaction_reference, paid_at)
- `shipments` (id, order_item_id, tracking_number, status, shipped_at, delivered_at)
- `reviews` (id, product_id, user_id, rating, comment)

---

## 8. Testing Strategy

- **Unit tests**: pricing decorators (each combination of discount/coupon/tax), payment strategy selection, order state transition legality (e.g., cannot go from `Pending` directly to `Delivered`).
- **Feature tests**: full auth flow, vendor product CRUD authorization, full checkout flow (cart → order → payment → state change), chain-of-responsibility rejection cases (e.g., checkout blocked when stock insufficient).
- Aim for realistic coverage on checkout and payment logic specifically, since that's the highest-risk business logic.

---

## 9. Definition of Done (per daily feature)

A day's feature is considered "done" when:
1. Code follows the layered architecture (Controller → Service → Repository) — no business logic in controllers.
2. At least one automated test covers the new behavior.
3. Feature is committed with a clear message and pushed to GitHub.
4. If the feature introduces or extends a design pattern, a one- or two-line comment in the code (or a `PATTERNS.md` entry) explains *why* that pattern was chosen there.

---

## 10. How to Use This PRD With an AI Coding Assistant

Suggested prompt structure for each day:
> "Here is the project PRD [attach/paste]. We are on Day X: [paste that day's line item]. Implement this following the architectural principles in Section 3. Show me the files you'll create/modify before writing full code, then generate the code, then suggest the test(s) to write."

This keeps the AI assistant scoped to one increment at a time instead of trying to generate the whole system at once, which matches the 30-day incremental delivery goal.
