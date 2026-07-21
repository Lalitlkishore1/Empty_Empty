# GalaxyOne V1 – System Architecture

Version: 1.0

---

# 1. Architecture Philosophy

GalaxyOne is built using a layered architecture.

Each layer has one responsibility.

Business rules must never be mixed with UI design.

Business logic must never be stored inside Elementor pages.

Business logic must never directly modify WooCommerce core files.

This allows future upgrades without rebuilding the project.

---

# 2. Technology Stack

Hosting
- Hostinger Business

CMS
- WordPress

E-commerce
- WooCommerce

Page Builder
- Elementor Pro

Custom Business Logic
- GalaxyOne Core Plugin

Theme
- Child Theme

Database
- WordPress + WooCommerce Tables
- Custom GalaxyOne Tables (only when necessary)

---

# 3. High-Level Architecture

                Customer
                    │
                    ▼
          WordPress Frontend
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
Elementor UI             WooCommerce
(Layout Only)         (Commerce Engine)
        │                       │
        └───────────┬───────────┘
                    ▼
          GalaxyOne Core Plugin
                    │
        ┌───────────┼───────────┐
        ▼           ▼           ▼
 Business Rules  Rewarded Ads  Delivery Logic
        │           │           │
        └───────────┼───────────┘
                    ▼
              WordPress Database

---

# 4. Responsibility of Each Layer

WordPress

Responsible for:

- Users
- Roles
- Settings
- Plugins
- Core CMS

---

WooCommerce

Responsible for:

- Products

- Cart

- Checkout

- Orders

- Payment Integration

WooCommerce should never contain GalaxyOne business logic.

---

Elementor

Responsible only for:

- Homepage

- Landing Pages

- Static Content

- Layout

No pricing logic.

No delivery logic.

No advertisement logic.

---

GalaxyOne Core Plugin

Responsible for ALL business logic.

Examples:

- Rewarded Advertisement Rules

- Offer Unlock Rules

- Delivery Rules

- Pricing Rules

- Flower Daily Pricing

- Water Business Rules

- Admin Configuration

- Future Expansion

This plugin becomes the brain of GalaxyOne.

---

# 5. Design Principles

Single Responsibility

Each module performs one task.

No Duplicate Logic

Business rules exist only once.

Configuration Driven

Administrator controls business behavior.

Future Ready

Adding new product categories should require configuration rather than redesign.

Maintainability

Every major feature must be isolated into its own module.

---

END OF SECTION 1

# 6. Project Structure

galaxyone/
│
├── wp-content/
│
├── plugins/
│   └── galaxyone-core/
│
├── themes/
│   └── galaxyone-child/
│
└── uploads/

----------------------------------------------------

# 7. GalaxyOne Core Plugin

galaxyone-core/

├── galaxyone-core.php
│
├── app/
│
│   ├── Admin/
│   ├── Customer/
│   ├── Products/
│   ├── Orders/
│   ├── Delivery/
│   ├── RewardedAds/
│   ├── Pricing/
│   ├── Offers/
│   ├── Notifications/
│   ├── Settings/
│   ├── Security/
│   └── Helpers/
│
├── assets/
│
│   ├── css/
│   ├── js/
│   └── images/
│
├── templates/
│
├── languages/
│
├── uninstall.php
│
└── readme.txt

----------------------------------------------------

# 8. Module Responsibilities

Admin

Responsible for:

• Dashboard

• Settings

• Reports

• Configuration

------------------------

Customer

Responsible for:

• Customer profile

• Order history

• Customer features

------------------------

Products

Responsible for:

• Product extensions

• Flower pricing

• Water rules

------------------------

Orders

Responsible for:

• Order workflow

• Status updates

• Validation

------------------------

Delivery

Responsible for:

• Delivery dates

• Delivery slots

• Service areas

------------------------

RewardedAds

Responsible for:

• Offer unlock

• Ad validation

• Reward logic

------------------------

Pricing

Responsible for:

• Normal prices

• Offer prices

• Reward prices

• Pricing priority

------------------------

Offers

Responsible for:

• Promotions

• Campaigns

• Free delivery

------------------------

Notifications

Responsible for:

• Customer notifications

• Admin notifications

------------------------

Settings

Responsible for:

• Global business configuration

------------------------

Security

Responsible for:

• Permissions

• Validation

• Nonces

------------------------

Helpers

Reusable utility functions.

----------------------------------------------------

# 9. Design Rules

Every module:

• Independent

• Small

• Reusable

• Testable

• Well documented

Modules communicate only through defined interfaces.

No module directly edits another module's data.

----------------------------------------------------

# 10. Coding Standards

• PSR-4 Autoloading

• Namespaces

• Object-Oriented PHP

• WordPress Coding Standards

• Minimal global functions

• Dependency injection where appropriate

• Strict separation of UI and business logic

----------------------------------------------------

END OF SECTION 2

# 11. Database Philosophy

GalaxyOne follows a "Reuse Before Create" strategy.

Priority Order:

1. Use WordPress tables.
2. Use WooCommerce tables.
3. Create GalaxyOne custom tables only if necessary.

This minimizes maintenance and maximizes compatibility.

----------------------------------------------------

# 12. WordPress Tables

Use existing tables for:

• Users

• User Roles

• User Meta

• Options

----------------------------------------------------

# 13. WooCommerce Tables

WooCommerce manages:

• Products

• Product Categories

• Product Images

• Orders

• Order Items

• Customers

• Coupons (Future)

GalaxyOne must extend WooCommerce instead of replacing it.

----------------------------------------------------

# 14. GalaxyOne Custom Tables

Create custom tables only for data that WooCommerce does not naturally support.

Examples:

galaxy_rewarded_ads

Stores:

• Campaign ID

• Product ID

• Ad Configuration

• Unlock Rules

----------------------------------------------------

galaxy_delivery_rules

Stores:

• Service Area

• Delivery Day

• Delivery Slot

• Capacity

• Delivery Charge

----------------------------------------------------

galaxy_flower_daily_prices

Stores:

• Product ID

• Selling Date

• Daily Price

• Availability

----------------------------------------------------

galaxy_activity_logs

Stores:

• Administrator

• Action

• Date

• Previous Value

• New Value

----------------------------------------------------

# 15. Relationships

WooCommerce Product

↓

GalaxyOne Rules

↓

Customer Interaction

↓

WooCommerce Order

↓

GalaxyOne Delivery

Business logic always extends WooCommerce.

Never replace WooCommerce order management.

----------------------------------------------------

# 16. WooCommerce Integration

GalaxyOne integrates using:

• Actions

• Filters

• REST APIs (Future)

Never modify WooCommerce core files.

----------------------------------------------------

# 17. Data Integrity

Every operation should validate:

• Product Exists

• Price Exists

• Delivery Exists

• Customer Exists

If validation fails,

return a user-friendly error.

----------------------------------------------------

# 18. Future Expansion

Future modules:

• Grocery

• Vegetables

• Fruits

• AI

should create their own modules without changing existing database structures whenever possible.

----------------------------------------------------

END OF SECTION 3

# 19. Core Engine Overview

GalaxyOne Core Plugin contains three independent engines.

1. Business Engine
2. Delivery Engine
3. Reward Engine

Each engine performs one responsibility only.

----------------------------------------------------

# 20. Business Engine

Responsible for:

• Product Rules

• Pricing Rules

• Offer Rules

• Flower Daily Pricing

• Water Pricing

• Inventory Rules

• Business Validation

Business Engine never handles advertisements.

Business Engine never handles deliveries.

----------------------------------------------------

# 21. Delivery Engine

Responsible for:

• Service Areas

• Streets

• Delivery Days

• Delivery Slots

• Delivery Charges

• Capacity Limits

• Delivery Status

Workflow

Order

↓

Validate Delivery Area

↓

Validate Delivery Capacity

↓

Assign Delivery Rules

↓

Save Delivery Information

----------------------------------------------------

# 22. Reward Engine

Responsible for:

• Rewarded Advertisement

• Offer Unlock

• Reward Validation

• Reward Expiration

• Advertisement Failure Handling

Workflow

Customer selects reward offer

↓

Validate campaign

↓

Launch rewarded advertisement

↓

Advertisement completed?

YES

↓

Unlock reward

↓

Update checkout

NO

↓

Keep normal price

----------------------------------------------------

# 23. Admin Dashboard

Administrator can manage:

Products

Orders

Customers

Flower Prices

Water Prices

Reward Campaigns

Delivery Rules

Business Settings

Reports

Logs

Dashboard Widgets

Today's Orders

Today's Revenue

Pending Orders

Out for Delivery

Completed Orders

Active Campaigns

Low Stock

----------------------------------------------------

# 24. Engine Communication

Business Engine

↓

Delivery Engine

↓

Reward Engine

↓

WooCommerce

↓

Order Created

Each engine communicates through service classes.

No engine directly modifies another engine's internal logic.

----------------------------------------------------

# 25. Error Handling

Every engine returns:

Success

Warning

Validation Error

System Error

Customer always receives readable messages.

Administrator receives detailed logs.

----------------------------------------------------

# 26. Future Expansion

Future engines can be added.

Examples

AI Engine

Analytics Engine

Recommendation Engine

Marketing Engine

Notification Engine

The existing engines should require no redesign.

----------------------------------------------------

END OF SECTION 4

# 27. Provider Layer

GalaxyOne must never depend directly on any third-party provider.

All external integrations must use Provider Interfaces.

Examples:

Advertisement Provider

Payment Provider

Notification Provider

Future AI Provider

----------------------------------------------------

Advertisement Provider Interface

Responsibilities

• Load Advertisement

• Validate Completion

• Return Success

• Return Failure

GalaxyOne uses the interface only.

Provider implementation is replaceable.

----------------------------------------------------

Payment Provider Interface

Future support:

• Razorpay

• PhonePe

• Cashfree

• Stripe

Business logic remains unchanged.

----------------------------------------------------

Notification Provider Interface

Future support:

• WhatsApp

• SMS

• Email

• Push Notification

----------------------------------------------------

# 28. Security Architecture

Every request must validate:

• User Permission

• Nonce

• Input Data

• Business Rules

• Product Availability

• Delivery Availability

Sensitive operations must be server-side only.

----------------------------------------------------

# 29. Performance Architecture

Priority Order

1.

Fast page loading

2.

Minimal database queries

3.

Lazy loading

4.

Caching

5.

Optimized images

6.

Minimal JavaScript

7.

Minimal plugin dependencies

----------------------------------------------------

# 30. Scalability

GalaxyOne architecture should support:

Small business

↓

City-wide business

↓

Multi-city business

↓

Multi-business platform

without redesigning the core plugin.

----------------------------------------------------

# 31. Maintainability

Every feature must have:

Single Responsibility

Clear Documentation

Independent Testing

No duplicated business logic

Configuration through Admin Panel

----------------------------------------------------

# 32. Upgrade Strategy

WordPress updates

↓

WooCommerce updates

↓

GalaxyOne Plugin updates

↓

Child Theme updates

Updates must not overwrite business logic.

----------------------------------------------------

# 33. Testing Strategy

Every feature requires:

Unit Testing

Integration Testing

Business Rule Testing

Mobile Testing

Admin Testing

Regression Testing

----------------------------------------------------

# 34. Final Architecture Principles

Business Logic

↓

Service Layer

↓

Provider Layer

↓

WordPress/WooCommerce

Never reverse this dependency.

----------------------------------------------------

END OF ARCHITECTURE.md
