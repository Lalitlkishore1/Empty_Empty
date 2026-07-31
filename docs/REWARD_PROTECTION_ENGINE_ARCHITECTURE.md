# GalaxyOne Reward Protection Engine Architecture

## Purpose

This document is the permanent architecture for GalaxyOne rewarded advertisements.

The primary objective is profitable product sales. A reward may offset part of a customer discount only when the completion is independently trusted, the resulting financial effect is deterministic, and fraud loss is bounded and measurable.

Rewards, discounts, prices, redemptions, orders, cancellations, refunds, and COD outcomes are financial operations. Browser-provided completion, reward, price, or redemption state is never authoritative.

## SECTION A — Permanent V1 Foundation Required Before Production Launch

### 1. Business Goals

The foundation must:

- Allow eligible customers to unlock a product offer after trusted provider-side verification.
- Preserve normal-price purchasing when rewards are unavailable.
- Keep reward discounts financially traceable from campaign through order outcome.
- Bound fraud loss through deterministic eligibility, verification, redemption, and reversal rules.
- Support future providers without changing checkout, pricing, order, or financial-history logic.
- Support future fraud decision engines without changing reward business rules or financial records.

### 2. Core Architectural Principles

- Provider-specific code is isolated behind a provider contract.
- Verification is server-authoritative and fails closed.
- Financial history is append-only; corrections are compensating entries, not rewrites.
- Current operational state is separate from immutable financial history.
- Every externally retried operation has an idempotency key.
- Every concurrent state transition is protected by database-backed conditional updates or transactions.
- Pricing remains authoritative in the pricing layer.
- Checkout applies only a trusted, server-issued reward entitlement.
- Order lifecycle handling owns financial settlement and reversal decisions.
- Activity logging records operational events without storing customer contact data or secrets.

### 3. Architectural Boundaries

| Boundary | Responsibility | Must not own |
| --- | --- | --- |
| Provider adapter | Provider launch, provider-side verification, normalized verification result | Pricing, cart totals, WooCommerce orders |
| Reward orchestration | Entitlement lifecycle, verification coordination, replay protection | Provider-specific protocol details |
| Reward ledger | Immutable reward and discount financial history | Browser interaction flow |
| Fraud decision service | Deterministic allow, deny, hold, or review decisions | Provider API calls, price calculation |
| Pricing | Authoritative product and offer prices, immutable price snapshots | Provider verification |
| Checkout | Validate and apply a trusted entitlement to a cart/order | Provider callback handling |
| Order lifecycle | Settle, reverse, or hold reward financial effects after order events | Campaign administration |
| Campaign administration | Configure eligible campaigns and limits | Direct price mutation or reward redemption |
| Audit and monitoring | Security, operational, and financial evidence | Business-state mutation |

### 4. Provider-Independent Advertisement Abstraction

The permanent provider abstraction must represent only provider-neutral concepts:

- Provider identity.
- Provider availability.
- A server-created reward interaction reference.
- A provider interaction reference.
- A provider-side verification request.
- A normalized verified completion attestation.
- A normalized permanent or retryable failure result.

The reward engine must not expose provider credentials, raw callback payloads, provider-specific reward state, or provider-specific completion semantics to checkout, pricing, orders, templates, or browser JavaScript.

A provider adapter may begin an interaction and verify completion only through the approved provider’s documented production mechanism. The adapter returns a normalized result that identifies the GalaxyOne reward event, provider interaction, verification timestamp, provider verification reference, and verification outcome.

### 5. Trusted Provider-Side Verification Architecture

A reward becomes eligible for redemption only after all of the following are true:

1. GalaxyOne created a server-side reward event for an eligible campaign and customer identity.
2. The provider adapter establishes a provider interaction bound to that event.
3. GalaxyOne receives or retrieves a provider-authoritative completion result through the approved provider contract.
4. The adapter verifies authenticity, freshness, replay resistance, event binding, and completion status according to the provider specification.
5. The reward engine atomically records the verified completion and creates a trusted entitlement.
6. Fraud decisioning permits the entitlement.

Browser requests may initiate a user interaction and may carry a GalaxyOne event token, but they cannot establish completion. A browser completion request can only trigger server-side verification when the approved provider contract permits it; it can never supply the proof itself.

### 6. Permanent Reward Lifecycle

The permanent reward lifecycle is:

`created → interaction_started → verification_pending → verified → entitled → applied_to_order → settled`

Terminal or corrective states are:

`rejected`, `expired`, `cancelled`, `reversed`, `refunded`, `cod_refused`, and `fraud_hold`.

Transitions must be explicit, timestamped, idempotent, and authorized by the owning boundary. A terminal transition cannot be silently overwritten. A correction creates a compensating financial record and a new lifecycle transition.

### 7. Financial Reward Lifecycle

A reward entitlement is not cash and is not a mutable price override. It is a limited financial authorization to apply a defined discount to a defined eligible product and order context.

The lifecycle is:

1. Campaign defines a maximum authorized offer.
2. Verified completion creates an entitlement with a fixed offer snapshot and expiry.
3. Pricing produces an immutable price snapshot using that entitlement.
4. Checkout records the applied reward allocation against the order.
5. Order creation records a pending financial application.
6. Order lifecycle settles, reverses, or adjusts the application according to the approved cancellation, refund, and COD policy.
7. Financial reporting derives totals from immutable ledger entries.

### 8. Financial Protection Model

The foundation must enforce:

- A campaign offer price is lower than the authoritative current price when created and when applied.
- The reward allocation is fixed in the order-item price snapshot.
- A reward may be applied only once unless a later compensating policy explicitly creates a new entitlement.
- The maximum financial exposure is bounded by campaign limits, customer limits, entitlement expiry, and order-level application rules.
- A reward cannot be used for a different product, customer identity, campaign, or order than the recorded entitlement permits.
- Cancellation, refund, and COD outcomes create traceable financial decisions rather than silently mutating redemption history.
- Normal-price purchasing remains available if any reward component is unavailable.

### 9. Reward Ledger Architecture

A dedicated append-only reward ledger must become the authoritative financial record.

Each entry records:

- Ledger entry identifier.
- Reward event and entitlement identifiers.
- Campaign and provider identifiers.
- Customer identity reference.
- Product, order, and order-item references where applicable.
- Entry type.
- Monetary amount and currency representation consistent with WooCommerce pricing.
- Immutable price-snapshot reference.
- Prior-entry or reversal reference when applicable.
- Idempotency key.
- Effective timestamp.
- Safe operational metadata.

Required entry types include entitlement issuance, order application, settlement, cancellation reversal, refund reversal, COD-refusal reversal, expiry, and fraud hold or release.

The ledger never stores raw provider credentials or customer contact information. It is never recalculated by rewriting history.

### 10. Audit Architecture

Activity logging remains operational evidence. The reward ledger remains financial evidence.

Audit records must identify:

- The action and responsible system boundary.
- The reward, campaign, provider, order, or ledger identifiers needed for investigation.
- The authorized actor category when an administrator action is involved.
- The verification and fraud-decision outcome.
- Safe failure category and correlation identifier.

Audit records must not contain raw provider payloads, credentials, signatures, customer contact details, or browser-provided untrusted price state.

### 11. Database Architecture

The existing campaign and event tables remain historical operational records. Production capability requires additive forward-only migrations for:

- Provider interaction and verification references.
- Immutable entitlement records.
- Append-only reward ledger entries.
- Idempotency records or idempotency keys on each externally retried financial mutation.
- Fraud-decision records and safe reason codes.
- Order-item reward application references.
- Necessary lookup indexes for customer, campaign, provider, order, entitlement, status, expiry, and idempotency.

All financial identifiers must be immutable. Mutable operational status may exist only where a corresponding immutable lifecycle or ledger record preserves the history.

### 12. Idempotency Architecture

The following actions require server-generated stable idempotency keys:

- Reward-event creation.
- Provider interaction start.
- Provider completion verification.
- Entitlement issuance.
- Cart-to-order reward application.
- Ledger-entry creation.
- Order settlement, cancellation, refund, and COD reversal.

A key is scoped to its operation and immutable attributes. Reuse with the same attributes returns the original result. Reuse with different attributes fails safely. A completed or terminal operation cannot be repeated as a new financial action.

### 13. Concurrency Architecture

Reward-event mutation, entitlement issuance, redemption, and ledger application must use transaction boundaries appropriate to the underlying database operation.

The system must:

- Lock or conditionally update the current event or entitlement state before transition.
- Enforce one successful entitlement issuance per verified event.
- Enforce one successful order application per entitlement.
- Enforce one financial settlement or compensating reversal per order outcome.
- Re-check status after acquiring a lock.
- Retry only recognized transient database failures with the same idempotency key.
- Fail closed when transaction outcome is uncertain.

### 14. Product Reward Architecture

Rewards are product-scoped through the catalog product identity used by authoritative pricing.

A reward entitlement records:

- The catalog product identity.
- The campaign identity.
- The verified offer price or discount allocation.
- The customer identity binding.
- The validity window.
- The permitted quantity and order-use rules.

Variations must resolve through the existing catalog-product normalization path before campaign lookup, pricing, entitlement validation, and redemption.

### 15. Campaign Architecture

A campaign is a versioned commercial policy, not a mutable source of historical pricing.

Campaign administration must support:

- Provider identity.
- Eligible product identity.
- Offer definition.
- Active, paused, and scheduled state.
- Start and end boundaries.
- Customer and campaign exposure limits.
- Fraud-policy reference.
- Version or effective-time identity.

Historical events, entitlements, price snapshots, orders, and ledger entries retain their original campaign and offer values even after campaign changes.

### 16. Customer Identity Architecture

The existing customer-hash approach is preserved as a privacy-oriented identity reference, but production rewards require an explicit identity-binding policy.

The policy must define the permitted identity sources for authenticated customers and guests, the session-to-order continuity rule, and the circumstances in which an entitlement becomes invalid. The reward engine uses the identity reference rather than contact data.

Identity changes, account changes, and checkout identity mismatch must fail closed or require a new verified entitlement according to the approved policy.

### 17. Fraud Decision Architecture

Fraud decisions are a separate deterministic contract with four outcomes:

- Allow.
- Deny.
- Hold.
- Review.

The reward orchestration layer requests a decision before entitlement issuance and before order application. It receives only a normalized decision, reason code, risk score or band, and correlation identifier.

The initial implementation may use deterministic rules. Future external fraud services or AI systems must implement the same decision contract and must not alter pricing, order, provider, or ledger logic directly.

### 18. Fraud Signal Architecture

Signals may include only data lawfully available to the reward engine and necessary for the approved policy, such as:

- Verified provider interaction and completion references.
- Customer identity reference.
- Campaign and product identity.
- Event timing and expiry.
- Repeated failed verification or replay attempts.
- Prior reward and ledger outcomes.
- Order, cancellation, refund, and COD-refusal outcomes.
- Device, network, or provider signals only when approved by the provider and product policy.

Signals are separated from decisions. Raw sensitive signals are retained only when necessary, securely stored, access-controlled, and excluded from activity logs.

### 19. Risk Architecture

Risk must be measurable through:

- Discount exposure authorized.
- Discount exposure applied.
- Discount exposure settled.
- Reversed or refunded reward value.
- COD refusal value.
- Expired and unused entitlement value.
- Verification failure rate.
- Replay and duplicate-attempt rate.
- Fraud deny and hold rate.
- Provider availability and verification latency.

Campaign limits, customer limits, and provider-level circuit breaking provide bounded loss. A provider or campaign may be disabled without changing normal-price checkout.

### 20. Checkout Integration

Checkout receives only a trusted entitlement reference and immutable price snapshot from the reward engine.

Checkout must:

- Revalidate the entitlement, campaign, product, customer identity, expiry, fraud decision, and price before order creation.
- Apply the reward only through the authoritative price snapshot path.
- Persist the reward application reference on the WooCommerce order item.
- Never calculate a reward price from request data.
- Fail to normal price or reject only the reward application when reward validation fails, according to the approved checkout policy.

### 21. Order Lifecycle Integration

Order lifecycle handling creates the financial outcome for every applied reward.

An order application is pending until the order reaches the approved settlement condition. Approved status transitions determine whether the ledger records settlement, cancellation reversal, refund reversal, or COD-refusal reversal.

WooCommerce CRUD and HPOS-compatible APIs remain the authority for orders and statuses. Reward logic consumes those lifecycle events without duplicating order state.

### 22. Cancellation Handling

When an order is cancelled before settlement, the engine must create an immutable cancellation-reversal ledger entry and transition the reward application to its terminal cancellation state.

Whether a replacement entitlement is issued is an explicit business policy and must not be inferred from deletion or status mutation. The original entitlement and financial history remain preserved.

### 23. Refund Handling

A refund requires an immutable reward financial adjustment linked to the WooCommerce refund and the affected reward application.

Full and partial refund behavior must be deterministic, amount-bounded, idempotent, and derived from order-item reward allocation. Refund processing never edits the original application or settlement ledger entry.

### 24. COD Handling

COD introduces a post-checkout financial risk. A reward applied to a COD order remains pending until the approved COD settlement event.

If COD is refused or the order is not completed under the approved policy, the engine records a COD-refusal reversal. Repeated COD-refusal outcomes become available to the isolated fraud-decision service as approved signals.

### 25. Failure Recovery

Provider failure, verification failure, database failure, checkout retry, order retry, worker failure, and delayed callback delivery must preserve the existing state and return a safe result.

Recovery always begins by reading the idempotent operation and current lifecycle state. It must not create a replacement financial action merely because the prior result is uncertain.

### 26. Retry Handling

Retries are bounded and restricted to recognized transient failures. They reuse the original operation idempotency key and correlation identifiers.

Invalid input, authorization failures, expired events, provider-verification failures, fraud denials, conflicting idempotency reuse, and terminal lifecycle states are not retried automatically.

### 27. Replay Protection

Replay protection requires:

- One-time provider completion references where provided.
- Idempotent reward-event, completion, entitlement, application, and ledger mutations.
- Customer, campaign, product, and event binding.
- Expiry checks at every transition.
- Rejection of duplicate or mismatched provider verification references.
- Replay-attempt audit records without sensitive payload retention.

### 28. Feature Flag Architecture

Feature controls are server-side and fail closed.

Required controls are:

- Global reward-engine enablement.
- Provider enablement.
- Campaign enablement.
- Customer eligibility.
- Fraud hold or review.
- Emergency provider disablement.
- Production environment permission.

No browser setting can enable a provider, campaign, or price. Feature control changes are capability-protected and activity-logged.

### 29. Emergency Recovery Architecture

Emergency controls must support:

- Disabling one provider.
- Pausing campaigns.
- Blocking new interactions.
- Blocking new entitlements.
- Blocking new reward applications.
- Continuing normal-price purchasing.
- Continuing order fulfilment and financial reporting.
- Preserving all existing events, entitlements, orders, and ledger records.

Emergency action never deletes financial history or changes completed order prices. Recovery uses explicit resume decisions and audit records.

### 30. Deployment Architecture

Production deployment requires:

- An approved provider integration and verification contract.
- Provider credentials held outside source control.
- Explicit production enablement only after end-to-end verification.
- Callback or server-verification configuration documented from the approved provider specification.
- Cache bypass or variation for reward interactions and reward-sensitive checkout state.
- External cron for reward expiry and deferred recovery tasks where those tasks are enabled.
- Staging validation using the same provider integration model where supported.

### 31. Monitoring Architecture

Production monitoring must track:

- Provider availability and verification outcomes.
- Verification latency and retry rate.
- Reward lifecycle transition failures.
- Idempotency collisions and replay attempts.
- Fraud decision distribution.
- Ledger application, settlement, reversal, and reconciliation failures.
- Reward discounts authorized, applied, settled, and reversed.
- Normal-price fallback availability.

Alerts must expose safe identifiers and categories, never credentials, provider secrets, raw payloads, or customer contact data.

### 32. Financial Reporting Architecture

Reports derive financial reward amounts from immutable ledger entries and WooCommerce order outcomes.

Required report dimensions include campaign, provider, product, period, lifecycle status, settlement state, refund state, cancellation state, and COD outcome. Reports must distinguish authorized, applied, settled, reversed, and unrecoverable reward cost.

### 33. Security Architecture

The engine requires:

- Server-side authorization for administration.
- Nonce validation for browser-initiated actions.
- Input validation and output escaping under repository conventions.
- Prepared database queries.
- Provider credential isolation from source control and logs.
- Provider-side verification before entitlement issuance.
- Strict customer identity binding.
- Expiry, one-time redemption, idempotency, and replay prevention.
- Safe public errors and detailed internal audit categories.
- Least-privilege access to operational and financial reward records.

### 34. Testing Architecture

Testing must cover:

- Provider adapter contract behavior with provider-controlled fixtures.
- Verification success, failure, replay, delayed delivery, and retry behavior.
- Entitlement and ledger idempotency.
- Concurrent completion and redemption attempts.
- Cart and checkout price revalidation.
- Order creation, cancellation, full and partial refund, COD completion, and COD refusal.
- Fraud allow, deny, hold, and review behavior.
- Feature-flag and emergency-disable behavior.
- Migration upgrades and data preservation.
- Production-like staging end-to-end validation without trusting browser completion state.

### 35. Repository Evolution Strategy

Evolution is additive:

- Preserve original creation migrations.
- Add forward-only migrations for production provider, entitlement, ledger, idempotency, fraud, and reporting needs.
- Preserve existing public reward entry points while routing them through the permanent orchestration boundary.
- Maintain provider adapters as isolated implementations.
- Keep pricing, checkout, order lifecycle, fraud decisions, and financial history independently evolvable.

### 36. Backward Compatibility Strategy

Existing reward campaigns and events remain readable as legacy operational records.

A forward migration assigns no fabricated historical provider verification or ledger evidence. Legacy staging events remain non-production records and must not be promoted to production entitlements. New production paths use the permanent records and lifecycle only.

### 37. Migration Strategy

Migrations must be forward-only, repeatable, verified before schema-version advancement, and safe after partial execution.

Each migration must:

- Preserve existing campaign and event rows.
- Add indexes before relying on new lookup paths.
- Backfill only facts derivable without ambiguity.
- Leave unknown historical financial state explicitly legacy rather than inventing ledger entries.
- Reconcile derived operational state without rewriting immutable financial history.
- Fail closed when integrity cannot be verified.

### 38. Existing Component Disposition

| Existing component | Disposition | Repository-supported reasoning |
| --- | --- | --- |
| `AdvertisementProviderInterface` | Preserve and Extend | It already isolates provider identity, interaction start, and verification; it needs production verification-result semantics without provider details leaking outward. |
| `StagingAdvertisementProvider` | Preserve | It is explicitly a non-production provider and remains suitable for local, development, and staging validation. |
| `RewardedAdsModule` | Preserve and Extend | It owns WordPress hooks, shortcode, AJAX, price application, redemption registration, and cleanup scheduling, but currently blocks all production registration. |
| `RewardCompletionService` | Migrate | It currently resolves only the staging provider and accepts completion through the staging path; it must become provider-neutral orchestration backed by trusted verification. |
| `RewardCampaignRepository` | Migrate | It currently permits only the `staging` provider and blocks production reads and writes. |
| `RewardEventRepository` | Migrate | It already records customer-bound, expiring, one-time events, but its staging-only gate and schema do not provide permanent provider-verification, idempotency, or financial-ledger architecture. |
| `RewardRedemptionService` | Migrate | It already supplies a trusted price context and one-time redemption path, but must support production lifecycle settlement and compensating financial outcomes. |
| `RewardEligibilityService` | Preserve and Extend | It provides campaign and customer-hash entry points; production requires explicit identity-binding and fraud-decision integration. |
| `CreateRewardCampaignsTable` | Preserve | Original creation migration must remain unchanged; later needs are additive migrations. |
| `CreateRewardEventsTable` | Preserve | Original creation migration must remain unchanged; legacy events must be preserved without fabricated financial history. |
| Rewarded-offer frontend template and JavaScript | Replace | The existing browser flow is specific to the staging interaction and cannot be the production verification authority. |
| Reward campaign administration template | Preserve and Extend | It supplies the administrative entry point but requires provider-neutral campaign controls and production-safe policy validation. |
| `PriceSnapshotService` | Preserve and Extend | It already creates immutable authoritative price snapshots and is the correct pricing boundary for a trusted entitlement reference. |
| Reward activity-log events | Preserve and Extend | Existing safe event logging is useful, but must be complemented by immutable financial-ledger records and provider-safe audit categories. |
| Reward expiry scheduled task | Preserve and Extend | Expiry is required permanently, with idempotent lifecycle and ledger handling. |
| Existing staging reward tests | Preserve | They remain regression coverage for the staging provider and must not be treated as production-provider verification coverage. |

## SECTION B — Extensions That Can Safely Be Added Later

The following capabilities are intentionally deferred because the Section A boundaries allow them without reconstruction:

- Additional production advertisement providers.
- Provider-specific client SDK integrations.
- Server-to-server provider verification alternatives.
- Provider webhooks where supported by the approved provider.
- Advanced rules-based fraud scoring.
- External fraud services.
- AI-assisted fraud decisioning through the fraud-decision contract.
- Customer reward limits based on additional approved signals.
- Provider performance optimization and campaign allocation.
- Advanced financial analytics and reconciliation exports.
- Promotional wallet, loyalty, or cross-product reward programs.

## Permanent Implementation Order

1. Obtain the approved production provider specification and trusted verification contract.
   - Validation gate: provider identity, start flow, completion proof, replay behavior, credentials, and callback or verification method are approved.
   - Review: `AdvertisementProviderInterface`, `RewardCompletionService`, `StagingAdvertisementProvider`, deployment documentation.

2. Define the provider-neutral production verification, entitlement, ledger, fraud-decision, and feature-control contracts.
   - Validation gate: no provider-specific field is required by pricing, checkout, order, or ledger boundaries.
   - Review: reward services, `PriceSnapshotService`, cart and checkout modules, order operations, activity logging.

3. Add forward-only database migrations for permanent records and indexes.
   - Validation gate: upgrade, repeatability, integrity, preservation, and rollback-compatible tests pass.
   - Review: existing reward migrations, `SchemaManager`, uninstall behavior, integration migration tests.

4. Implement reward lifecycle and immutable ledger repositories.
   - Validation gate: idempotency, concurrency, append-only history, and financial reconciliation tests pass.
   - Review: `RewardEventRepository`, `RewardRedemptionService`, activity logging, order services.

5. Implement the approved provider adapter.
   - Validation gate: provider fixtures prove only trusted provider-side verification can issue an entitlement.
   - Review: provider interface, completion orchestration, deployment secret conventions, reward tests.

6. Migrate completion and campaign orchestration to the permanent contracts.
   - Validation gate: staging behavior remains intact; production remains disabled until an approved provider is configured.
   - Review: `RewardCompletionService`, `RewardCampaignRepository`, `RewardEligibilityService`, module registration.

7. Integrate entitlement application with authoritative pricing, cart, checkout, and order-item persistence.
   - Validation gate: forged browser state cannot change price; retry and duplicate order paths remain deterministic.
   - Review: `PriceSnapshotService`, cart recalculation, checkout module, order lifecycle services.

8. Implement settlement, cancellation, refund, and COD financial outcomes.
   - Validation gate: every outcome creates the correct immutable ledger effect exactly once.
   - Review: WooCommerce order operations, order-status handling, notification and activity-log paths.

9. Add fraud decisions, emergency controls, monitoring, reporting, and production configuration.
   - Validation gate: provider or fraud failure cannot interrupt normal-price purchasing; measurable risk and operational evidence are available.
   - Review: module, deployment guide, operations runbook, reporting, scheduled jobs, test plan.

10. Enable production only after provider-specific integration, security, migration, concurrency, financial-lifecycle, and end-to-end staging gates pass.
   - Validation gate: an approved production launch record contains all required evidence.
   - Review: deployment guide, release checklist, security operations, production test plan.
