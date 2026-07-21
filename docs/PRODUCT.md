# GalaxyOne V1 – Product Requirements Document (PRODUCT.md)

Version: 1.0
Status: Draft
Product Name: GalaxyOne
Project Type: Local E-Commerce Platform
Development Method: AI-Assisted (ChatGPT + Codex)
Platform:
- WordPress
- WooCommerce
- Elementor Pro
- GalaxyOne Core Plugin
- Child Theme

---

## 1. Vision

GalaxyOne is a local commerce platform designed to provide a simple, fast, and affordable shopping experience.

Version 1 focuses on delivering Water and Blooms (Flowers & Fragrances) with a clean, professional user experience and a scalable technical foundation.

GalaxyOne is designed to expand into additional categories in future versions without rebuilding the core platform.

---

## 2. Mission

Deliver essential daily products with:

- Simple ordering
- Transparent pricing
- Rewarded savings opportunities
- Fast local delivery
- Professional user experience
- Scalable architecture

---

## 3. Version 1 Scope

Included

✔ 20L Water

✔ Blooms
- Fresh Flowers
- Flower Fragrances

✔ Homepage

✔ Product Pages

✔ Shopping Cart

✔ Checkout

✔ Order Management

✔ Delivery Scheduling

✔ Rewarded Advertisement System
(Optional offers only)

✔ Admin Dashboard

---

Not Included

✘ Grocery

✘ Vegetables

✘ Fruits

✘ Treasure Hunt

✘ Galaxy Universe Characters

✘ Intro Animations

✘ Loyalty Program

✘ Mobile Application

These features belong to future versions.

---

# 4. Business Model

GalaxyOne is a local delivery platform focused on essential daily products.

Version 1 Categories:

• Water
• Blooms (Flowers & Fragrances)

Revenue Sources:

1. Product Sales
2. Optional Rewarded Advertisements
3. Future Premium Services (Not V1)

---

# 5. Core Business Rules

## 5.1 Product Categories

Active

• Water
• Blooms

Coming Soon

• Vegetables
• Fruits
• Grocery
• Other Services

---

## 5.2 Pricing Rules

Water

• Prices change only when updated by the admin.
• Offer prices may be scheduled.

Blooms

• Prices can change daily.
• Each flower has its own daily selling price.

---

## 5.3 Rewarded Advertisement Rules

Rewarded Ads are OPTIONAL.

Customers always have two choices.

Option A

Buy at Normal Price.

No advertisement.

Option B

Unlock Special Offer.

Watch the required sponsored video(s).

Receive the unlocked offer.

Rewarded Ads are available only on products selected by the administrator.

---

## 5.4 Offer Rules

Offers may include:

• Lower Product Price

• Free Delivery

• Limited-Time Promotion

Offers are controlled only by the administrator.

---

## 5.5 Delivery Rules

Every product displays:

• Delivery Date

• Delivery Type

• Delivery Charge

The administrator controls delivery availability.

---

## 5.6 Customer Rules

Customers may:

• Browse Products

• Add Products to Cart

• Purchase Multiple Quantities

• Unlock Eligible Offers

Customers cannot unlock offers unless they satisfy the required conditions.

---

## 5.7 Admin Rules

Administrator controls:

• Products

• Prices

• Offers

• Advertisements

• Delivery Settings

• Orders

• Categories

No business rule is hard-coded.

Everything should be configurable from the admin panel whenever possible.

---

# 6. Future Expansion

The architecture must support adding:

• Grocery

• Vegetables

• Fruits

• Additional Services

without rebuilding the existing platform.

---

# 7. Customer Journey

## Step 1 – Visit Website

Customer opens GalaxyOne.

Homepage loads immediately.

No intro animation.

No forced advertisement.

---

## Step 2 – Homepage

Homepage displays:

• Water

• Blooms

• Expanding Soon Categories

The homepage must be simple, mobile-friendly, and fast.

---

## Step 3 – Select Category

Customer selects:

• Water

or

• Blooms

---

## Step 4 – Product List

Each product card displays:

• Product Image

• Product Name

• Quantity / Unit

• Normal Price

• Offer Price (if available)

• Delivery Information

• Add to Cart button

If an optional Rewarded Offer exists, show:

"🎁 Unlock Special Offer"

---

## Step 5 – Rewarded Offer Flow

Customer has two choices.

### Option A

Continue normally.

↓

Pay Normal Price.

↓

Checkout.

### Option B

Tap:

Unlock Special Offer

↓

Show Rewarded Advertisement

↓

Advertisement completed successfully

↓

Offer unlocked

↓

Updated price or delivery benefit applied

↓

Checkout

If the advertisement is not completed,

the offer remains locked,

and the customer continues with the normal price.

---

## Step 6 – Shopping Cart

Customer can:

• Increase Quantity

• Decrease Quantity

• Remove Products

• View Total

• View Delivery Information

• Continue Shopping

• Proceed to Checkout

---

## Step 7 – Checkout

Customer enters:

• Name

• Mobile Number

• Delivery Address

• Landmark (Optional)

• Order Notes (Optional)

Customer selects available payment method.

Review order.

Place Order.

---

## Step 8 – Order Confirmation

After successful order:

Display:

Order Number

Estimated Delivery Date

Order Summary

Thank You Message

---

# 8. Customer Experience Rules

GalaxyOne must provide:

• Fast page loading

• Simple navigation

• Minimal clicks

• Mobile-first design

• Clear pricing

• Transparent offers

• Easy ordering

Customers should never feel confused while placing an order.

Every important action should be completed with as few steps as possible.

---

# 9. Product Management

GalaxyOne Version 1 supports two active categories:

• Water
• Blooms (Flowers & Fragrances)

The system must be designed so new categories can be added without changing the existing architecture.

Future categories:

• Vegetables
• Fruits
• Grocery
• Other Services

---

# 10. Water Products

Example:

20L Water

Administrator controls:

• Product Name
• Description
• Images
• Status
• Visibility

Pricing:

• Normal Price
• Offer Price
• Delivery Charge
• Delivery Date
• Offer Availability

Water prices normally change occasionally and are managed entirely by the administrator.

---

# 11. Blooms Products

Blooms includes:

• Fresh Flowers
• Flower Fragrances

Examples:

• Red Rose
• Paneer Rose
• Jasmine (Malli)
• Mullai
• Samanthi
• Violet Samanthi
• Other Flower Varieties

Administrator controls:

• Daily Selling Price
• Availability
• Images
• Description
• Delivery Information

Flower prices may change every day.

The admin should be able to update prices quickly.

---

# 12. Product Card

Every product card should display:

• Product Image

• Product Name

• Unit / Quantity

• Normal Price

• Offer Price (if available)

• Delivery Information

• Add to Cart

If eligible,

display:

🎁 Unlock Special Offer

---

# 13. Rewarded Advertisement System

Rewarded advertisements are OPTIONAL.

Customers always have two choices.

Option A

Buy Normally.

↓

Normal Price.

↓

Checkout.

Option B

Unlock Special Offer.

↓

Watch Required Rewarded Advertisement.

↓

Advertisement completed successfully.

↓

Offer unlocked.

↓

Checkout.

---

# 14. Advertisement Rules

Administrator decides:

• Which products support rewarded ads.

• Number of rewarded ads required.

• Offer validity.

• Discount amount.

• Free delivery eligibility.

Advertisements are never forced.

Products without rewarded advertisements work exactly like a normal e-commerce website.

---

# 15. Unlock Rules

Offer unlock occurs only after successful advertisement completion.

If the advertisement fails,

or is closed before completion,

the offer remains locked.

Customer can:

• Buy at Normal Price

or

• Retry the rewarded advertisement.

---

# 16. Quantity Rules

Customers can:

• Buy one quantity.

• Buy multiple quantities.

Quantity selector must update:

• Product Total

• Cart Total

• Checkout Total

automatically.

---

# 17. Future Compatibility

The rewarded advertisement system must be reusable.

Future categories can enable rewarded advertisements without rewriting the system.

The same architecture should support:

• Grocery

• Vegetables

• Fruits

• Future Services

---

# 18. Delivery System

## 18.1 Service Area

GalaxyOne delivers only within administrator-defined service areas.

Customers outside the service area cannot place orders.

---

## 18.2 Delivery Schedule

Administrator controls:

• Delivery Days
• Delivery Time Slots
• Delivery Availability
• Delivery Charges
• Free Delivery Offers

Every product displays its available delivery information before checkout.

---

## 18.3 Delivery Status

Each order follows this lifecycle:

1. Order Placed
2. Order Confirmed
3. Preparing
4. Out for Delivery
5. Delivered
6. Cancelled (if applicable)

---

# 19. Checkout System

Checkout should be simple and require the fewest possible steps.

Customer Information:

• Full Name
• Mobile Number
• Delivery Address
• Landmark (Optional)
• Order Notes (Optional)

Order Summary:

• Products
• Quantities
• Prices
• Discounts
• Delivery Charges
• Grand Total

Customer confirms the order before placing it.

---

# 20. Payment System

Version 1 Supported Methods:

• Cash on Delivery (COD)
• UPI on Delivery

Future Versions:

• Online Payment Gateway
• Wallet
• EMI
• Gift Cards

The payment architecture should allow future payment methods without redesign.

---

# 21. Order Management

Administrator can:

• View Orders
• Search Orders
• Filter Orders
• Update Order Status
• Cancel Orders
• Print Order Details
• Export Orders (Future)

---

# 22. Product Management

Administrator can:

• Create Products
• Edit Products
• Delete Products
• Enable/Disable Products
• Update Daily Flower Prices
• Update Water Prices
• Configure Rewarded Offers

---

# 23. Advertisement Management

Administrator controls:

• Rewarded Ad Availability
• Eligible Products
• Number of Ads Required
• Discount Amount
• Free Delivery Offers
• Campaign Start Date
• Campaign End Date

No code changes should be required to manage campaigns.

---

# 24. Customer Management

Administrator can:

• View Customers
• View Order History
• Search Customers
• Block/Unblock Customers (Future)

---

# 25. Dashboard

Dashboard should display:

• Today's Orders
• Pending Orders
• Orders Out for Delivery
• Delivered Orders
• Revenue Summary
• Top Selling Products
• Active Offers
• Low Stock Alerts (Flowers only if inventory is tracked)

The dashboard should provide a quick overview of daily operations.

---

# 26. Notifications

Version 1:

• Order Confirmation
• Order Status Updates

Architecture should support future integration with:

• WhatsApp
• SMS
• Email
• Push Notifications

---

# 27. Acceptance Criteria

GalaxyOne V1 is considered complete when:

✓ Homepage is functional.

✓ Water products work correctly.

✓ Blooms products work correctly.

✓ Optional rewarded advertisements work correctly.

✓ Shopping cart works.

✓ Checkout works.

✓ Orders are successfully created.

✓ Administrator can manage products, offers, prices, and orders.

✓ Website performs well on mobile devices.

✓ Future categories can be added without rebuilding the system.

---

# 28. Non-Functional Requirements

## Performance

The website must:

• Load quickly on mobile devices.

• Use optimized images.

• Minimize unnecessary plugins.

• Use caching where appropriate.

• Maintain smooth navigation.

Performance is a higher priority than animations.

---

## Mobile First

GalaxyOne is designed primarily for mobile users.

Every page must function correctly on:

• Android

• iPhone

Desktop support is required but mobile experience has higher priority.

---

## Accessibility

The website should:

• Use readable fonts.

• Maintain sufficient color contrast.

• Have clearly visible buttons.

• Support touch-friendly controls.

---

# 29. Security

Customer information must be protected.

Requirements:

• WordPress security best practices.

• Input validation.

• Output escaping.

• Nonce verification.

• Role-based permissions.

• Secure database queries.

No sensitive business logic should rely only on client-side JavaScript.

---

# 30. Architecture Constraints

Business logic belongs only inside:

GalaxyOne Core Plugin.

Elementor is used only for:

• Layout

• Design

• Editable content

WooCommerce handles:

• Products

• Cart

• Checkout

• Orders

Child Theme handles:

• Styling

• UI customization

Business rules must never be duplicated across multiple locations.

---

# 31. Coding Standards

Every custom feature should:

• Be modular.

• Be reusable.

• Be documented.

• Follow WordPress Coding Standards.

Avoid hardcoded values whenever possible.

Configuration should be managed from the admin panel.

---

# 32. Scalability

Version 1 must support future expansion without major redesign.

Future additions include:

• Grocery

• Vegetables

• Fruits

• Additional Services

The foundation should remain unchanged.

---

# 33. Version 1 Success Criteria

GalaxyOne V1 is successful when:

✓ Customers can browse products.

✓ Customers can place orders easily.

✓ Rewarded offers function correctly.

✓ Admin can manage products, prices, offers, and orders.

✓ Water and Blooms operate independently.

✓ Mobile experience is smooth.

✓ Website loads quickly.

✓ Architecture supports future expansion.

---

# 34. Requirement Freeze

After PRODUCT.md v1.0 is approved:

No new Version 1 features will be added unless:

1. A critical bug is found.

2. A business-critical requirement is missing.

3. The owner explicitly approves a change.

All other ideas move to Version 2.

---

END OF DOCUMENT

---

# 35. Inventory Management

## Water

Administrator manages:

• Available Quantity

• In Stock

• Out of Stock

Water products remain visible even if temporarily unavailable.

Customers see:

"Currently Unavailable"

instead of placing an invalid order.

---

## Blooms

Administrator manages:

• Daily Availability

• Daily Quantity

• Daily Price

Since flowers are perishable,

availability may change every day.

---

# 36. Pricing Priority

Pricing order:

Priority 1

Rewarded Offer Price

↓

Priority 2

Scheduled Offer Price

↓

Priority 3

Normal Price

Only one selling price applies during checkout.

---

# 37. Rewarded Advertisement Failure Rules

If:

• Advertisement fails

• Internet disconnects

• User closes advertisement

• Advertisement provider is unavailable

Then:

Offer remains locked.

Customer may:

• Retry

or

• Purchase at Normal Price.

---

# 38. Daily Flower Workflow

Administrator Daily Process

1.

Update today's flower prices.

2.

Update availability.

3.

Save changes.

Customers immediately see updated prices.

No restart required.

---

# 39. Delivery Capacity

Administrator may configure:

• Maximum deliveries

• Delivery slots

• Service areas

If delivery becomes unavailable,

customers are informed before checkout.

---

# 40. Error Messages

Display clear messages.

Examples:

• Product unavailable.

• Delivery unavailable.

• Offer expired.

• Advertisement unavailable.

Never display technical errors to customers.

---

# 41. Activity Log

Administrator actions should be recorded.

Examples:

• Product created

• Product edited

• Price changed

• Offer modified

• Order cancelled

This improves administration and troubleshooting.

---
