# Master Prompt — Managed Trading Demo Platform

You are the primary coding AI responsible for building a polished, production-quality **managed-trading demo/presentation platform**.

Your job is to turn the requirements below into a working web application while keeping the architecture simple, secure, maintainable, and visually polished.

Do **not** invent requirements that are not specified here.

When a requirement is genuinely ambiguous and could materially affect architecture or behavior, stop and ask one concise question before proceeding. Otherwise, make the most conventional web-development choice and explain it briefly.

The product is a **demo/simulation platform**. It does not execute real trades and does not connect to blockchains, exchanges, or trading APIs.

---

# 1. CORE PRODUCT

The platform presents a company offering managed cryptocurrency trading.

Users can:

- register
- verify email
- log in
- manage their profile/security
- fund their account using supported cryptocurrencies
- see pending deposits
- have approved deposits credited to their managed balance
- view their simulated managed-trading performance
- view their current tier and strategy
- view simulated cryptocurrency market data
- request withdrawals
- view transaction history
- receive notifications

Administrators control the simulated account state and operational workflows.

The system must clearly identify the environment as a demo/simulation where appropriate:

**"Demo Account · Simulated Performance"**

Do not present simulated performance as real trading results.

There is:

- no real trading engine
- no autonomous AI trader
- no exchange integration
- no blockchain API
- no automatic blockchain confirmation
- no wallet generation
- no real-time trading execution
- no guaranteed returns
- no fixed APY/profit promises

---

# 2. TECHNOLOGY STACK

## Frontend

- React
- TypeScript
- Vite
- Tailwind CSS

Use a feature-oriented structure.

Suggested:

```text
frontend/
├── src/
│   ├── components/
│   ├── features/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── portfolio/
│   │   ├── markets/
│   │   ├── wallet/
│   │   ├── transactions/
│   │   ├── notifications/
│   │   └── admin/
│   ├── layouts/
│   ├── pages/
│   ├── lib/
│   ├── hooks/
│   └── types/
└── ...
```

Do not add unnecessary frontend libraries simply because they are popular.

## Backend

- Laravel 13
- PHP 8.5
- MySQL
- Laravel Sanctum

Use Laravel's normal conventions rather than inventing a custom backend architecture.

Suggested:

```text
backend/
├── app/
│   ├── Models/
│   ├── Http/
│   ├── Policies/
│   └── ...
├── database/
│   ├── migrations/
│   └── seeders/
└── ...
```

---

# 3. AUTHENTICATION

Use Laravel Sanctum with first-party SPA cookie/session authentication.

Do NOT use JWT stored in localStorage.

Authentication must support:

- registration
- login
- logout
- email verification
- forgot password
- password reset
- authenticated sessions
- profile management
- password change
- optional user 2FA
- role-based authorization

User registration:

- full name
- email
- password
- country
- optional phone

Country should use a predefined searchable/select control.

User roles:

```text
user
admin
super_admin
```

Account status:

```text
active
suspended
```

Role and account status are separate concepts.

Admin authorization must be enforced server-side using Laravel authorization/middleware/policies.

Do not merely hide admin UI from users.

---

# 4. SECURITY

Use conventional Laravel security practices.

Required:

- password hashing
- CSRF protection
- secure session cookies
- HttpOnly cookies
- Secure cookies in production
- appropriate SameSite configuration
- server-side validation
- authorization policies/middleware
- rate limiting for sensitive endpoints
- session regeneration where appropriate
- HTTPS in production
- secrets in environment/server configuration
- no secrets in React
- backend is authoritative for financial/account state

Never trust the frontend for:

- balances
- tier qualification
- deposit approval
- withdrawal approval
- permissions
- transaction values
- account performance
- administrative actions

Use database transactions for financial state-changing operations.

Example:

```text
BEGIN
→ update account/status
→ create transaction
→ create audit log
→ COMMIT
```

Rollback everything if any required operation fails.

---

# 5. LANGUAGE

English only.

Do not build:

- language selection
- localization infrastructure
- translation files
- language preference
- i18n architecture

unless explicitly requested later.

---

# 6. CURRENCY

USD is the primary display currency.

Crypto amounts should also be displayed for crypto transactions.

Do not build:

- multi-currency user preferences
- FX conversion
- exchange-rate conversion
- currency selection

---

# 7. SUPPORTED CRYPTO ASSETS

Initial supported assets:

```text
BTC
ETH
LTC
USDT
```

Assets are admin-configurable.

Asset structure should support:

- id
- symbol
- name
- active
- network where applicable

USDT requires a network field because it can exist on multiple networks.

Do not build multi-network infrastructure yet.

The admin simply configures the demo network/address being used.

---

# 8. ADMIN WALLET ADDRESSES

Wallet addresses must be admin-configured.

Do NOT hardcode wallet addresses into React.

Do NOT generate wallets dynamically.

Do NOT connect to blockchain APIs.

Suggested structure:

```text
asset_wallets
├── id
├── asset_id
├── network
├── wallet_address
└── active
```

---

# 9. CRYPTO PRICING

Market prices are simulated and controlled by administrators.

Each asset can have:

- current simulated price
- 24h percentage change
- active/inactive state

No external exchange API.

No live market API.

No blockchain pricing API.

No real-time trading infrastructure.

Important:

A market price change must NOT automatically modify a user's account balance or performance.

There are two separate systems:

```text
Admin market price
        ↓
transaction USD valuation
market display
```

and:

```text
Admin account simulation
        ↓
managed balance
P/L
performance
```

These must remain independent.

---

# 10. HISTORICAL PRICE SNAPSHOTS

Whenever a crypto transaction/deposit is submitted and a USD valuation is calculated, store the price used at that moment.

Example:

```text
BTC price at submission = $67,500
Crypto amount = 0.052 BTC
USD value = $3,510
```

If the admin later changes BTC to $72,000, the historical transaction must still display:

```text
0.052 BTC
$3,510
```

Do not recalculate historical transaction USD values using the current market price.

---

# 11. MANAGED BALANCE MODEL

The platform uses a fully managed balance model.

Important account fields:

```text
managed_balance
pending_balance
total_profit_loss
performance_percentage
trading_status
tier_id
```

### Managed Balance

The user's current managed account value.

It changes through:

- approved deposits
- approved withdrawals
- administrator-controlled simulated performance/adjustments

### Pending Balance

Deposit funds submitted by the user but not yet approved.

Pending deposits do NOT count toward:

- managed balance
- total account value
- tier qualification

### Total Account Value

For this application:

```text
Total Account Value = Managed Balance
```

Do not introduce a separate available-allocation system.

---

# 12. DEPOSITS

Deposit flow:

```text
User selects crypto
        ↓
System displays configured wallet address
        ↓
User sends crypto externally
        ↓
User submits payment/transaction details
        ↓
Deposit = Pending
        ↓
Amount appears in Pending Balance
        ↓
Admin reviews
        ↓
Confirm
        ↓
Pending amount removed
        ↓
Amount credited to Managed Balance
```

If rejected:

```text
Deposit = Rejected
No managed balance credit
```

Deposit records should contain approximately:

```text
id
user_id
asset_id
crypto_amount
price_snapshot
usd_value
wallet_address
transaction_reference
status
submitted_at
reviewed_at
reviewed_by
timestamps
```

Statuses can include:

```text
pending
confirmed
rejected
```

Approved deposits must create the appropriate historical transaction and audit log.

---

# 13. WITHDRAWALS

Withdrawals are manually approved.

User submits:

- amount
- asset
- destination wallet address

The system must validate the requested amount against the user's withdrawable amount.

Use reservation-by-pending-withdrawal.

Do NOT immediately deduct the managed balance when a withdrawal is submitted.

Formula:

```text
withdrawable_amount =
managed_balance
-
sum(pending withdrawal amounts)
```

Example:

```text
Managed Balance: $10,000
Pending Withdrawal: $1,500

Withdrawable Amount: $8,500
```

The pending withdrawal effectively reserves the amount.

### Approval

When admin approves:

```text
BEGIN DATABASE TRANSACTION

deduct amount from managed_balance
mark withdrawal approved
create transaction
create audit log

COMMIT
```

### Rejection

When rejected:

```text
mark withdrawal rejected
release reservation
do not deduct managed balance
create audit log
```

A pending withdrawal does NOT change the user's tier.

Only actual Managed Balance determines tier.

---

# 14. TIERS

There are four tiers:

| Tier | Minimum Managed Balance |
|---|---:|
| Foundation | $100 |
| Momentum | $1,000 |
| Elevation | $5,000 |
| Apex | $25,000 |

Below $100:

```text
No qualifying tier
```

Tier qualification is automatic.

It is based ONLY on:

```text
Managed Balance
```

Not:

- pending deposits
- withdrawable amount
- pending withdrawals
- wallet balance
- crypto market value

Admin-configurable thresholds must be supported.

When Managed Balance changes, recalculate the user's tier.

Store the current `tier_id` on the account for convenient display, but treat the configured balance rules as authoritative.

Do not create four separate trading engines.

---

# 15. STRATEGY MODEL

Use one core managed-trading system with different strategy profiles.

### Foundation

Positioning:

- entry-level
- measured exposure
- diversification
- capital preservation emphasis
- steady monitoring
- conservative profile

### Momentum

Positioning:

- balanced
- market momentum
- diversified exposure
- active monitoring
- tactical allocation
- moderate profile

### Elevation

Positioning:

- advanced
- dynamic allocation
- broader opportunities
- more active management
- moderate-high profile

### Apex

Positioning:

- premium
- dynamic positioning
- broader market exposure
- comprehensive portfolio analysis
- high profile

These are descriptive demo strategy profiles.

Do NOT attach guaranteed percentages or fixed profits to them.

---

# 16. TIER CONFIGURATION

Tier data should be configurable rather than hardcoded throughout the frontend.

Suggested structure:

```text
tiers
├── id
├── name
├── minimum_balance
├── strategy_id
├── description
├── benefits
├── feature_access
├── display_settings
└── timestamps
```

Strategy:

```text
strategies
├── id
├── name
├── description
├── risk_profile
├── active
└── timestamps
```

Benefits and feature access may use JSON where appropriate, but do not over-engineer this.

---

# 17. USER PERFORMANCE

The application does not expose individual trades.

Users see account-level performance only.

Display:

- managed balance
- total P/L
- performance %
- current tier
- current strategy
- trading status
- performance history
- performance chart

The administrator controls the simulated account performance.

Use controlled performance snapshots.

Example:

```text
Jun 1   → $10,000
Jun 15  → $10,650
Jul 1   → $11,400
Aug 1   → $12,000
Sep 1   → $12,500
```

Performance snapshots should support:

```text
id
account_id
snapshot_date
account_value
profit_loss
performance_percentage
note
created_by
timestamps
```

Do not build an autonomous performance-generation engine.

---

# 18. ADMIN ACCOUNT SIMULATION

Admins can manage demo account state.

Admin controls include:

- managed balance
- pending balance where appropriate
- total P/L
- performance percentage
- trading status
- performance history
- performance snapshots
- appropriate transaction/account adjustments

If a manual adjustment changes financial state, it must create:

1. appropriate transaction/adjustment record
2. audit log

Do not silently mutate historical financial records.

For corrections, use explicit adjustment/correction records.

---

# 19. AUDIT LOG

Audit logging is a core architectural requirement.

Record administrative changes such as:

- Admin changed simulated balance
- Admin approved deposit
- Admin rejected deposit
- Admin approved withdrawal
- Admin rejected withdrawal
- Admin changed market price
- Admin changed tier configuration
- Admin created performance snapshot
- Admin changed account status

Suggested structure:

```text
audit_logs
├── id
├── admin_id
├── user_id
├── action
├── entity_type
├── entity_id
├── old_values
├── new_values
└── created_at
```

Use JSON for old/new values where appropriate.

Normal admins should not be able to alter audit history.

Example readable entry:

```text
Admin changed simulated balance from $5,000 → $12,500.
```

The audit log should answer:

- who changed it?
- what changed?
- which user/account?
- previous value?
- new value?
- when?

---

# 20. TRANSACTION ARCHITECTURE

Do not build a banking-grade double-entry accounting system.

Use a simple hybrid model:

```text
Current account state
+
specialized deposit/withdrawal records
+
unified transaction history
+
performance snapshots
+
audit logs
```

These concepts must remain separate.

### Transaction

A financial/account state-changing event.

Examples:

- deposit
- withdrawal
- account adjustment

### Performance Snapshot

A point-in-time simulated account value.

### Audit Log

An administrative/system action record.

Do not put performance snapshots into the user transaction history.

---

# 21. UNIFIED TRANSACTIONS

User Transactions page contains:

- deposits
- withdrawals
- account adjustments

Suggested fields:

```text
date
type
asset
crypto amount
USD value
status
reference
```

Filters:

```text
All
Deposits
Withdrawals
Adjustments
```

Admin transaction view is global and can filter by:

- user
- type
- asset
- status
- date

Do not arbitrarily edit historical transactions.

Use explicit correction/adjustment records.

---

# 22. MONEY PRECISION

Never use floating-point arithmetic for financial values.

Use fixed-precision DECIMAL database fields.

USD and crypto should have appropriate precision for their respective use cases.

Choose sensible precision during schema implementation and keep it consistent throughout the backend.

The backend is authoritative for all financial calculations.

---

# 23. USER DASHBOARD

Create a clean fintech-style dashboard.

### Overview

Display:

- Total Account Value
- Managed Balance
- Pending Balance
- Total P/L
- Performance %
- Current Tier
- Trading Status
- recent transactions
- performance chart
- quick Deposit action
- quick Withdraw action

### Portfolio

Display:

- managed balance
- P/L
- performance history
- current strategy
- tier
- strategy description
- account status

Do not display individual trades.

### Markets

Display:

- BTC
- ETH
- LTC
- USDT
- simulated current price
- 24h change
- basic charts
- market summary

### Wallet

Combine:

- Deposit
- Withdraw

Avoid unnecessary separate navigation pages.

### Transactions

Unified financial history.

### Notifications

User notifications.

### Settings

Include:

- Profile
- Security

Do not create unnecessary pages.

---

# 24. PROFILE

Profile contains:

- full name
- email
- country
- optional phone

Security contains:

- change password
- optional 2FA
- session/security controls if implemented

---

# 25. NOTIFICATIONS

Build a simple notification system.

Do not over-engineer it.

Potential notification events:

- deposit submitted
- deposit approved
- deposit rejected
- withdrawal submitted
- withdrawal approved
- withdrawal rejected
- account adjustment
- tier changed
- important account/security event

Use Laravel's notification architecture where appropriate.

---

# 26. EMAIL

Use Laravel's mail abstraction.

Initially support development/demo mail configuration.

Architecture should allow switching the actual mail provider later without rewriting authentication/business logic.

Use it for:

- email verification
- password reset
- future account notifications

Do not build an elaborate email-management platform.

---

# 27. ADMIN DASHBOARD

Admin navigation:

```text
Dashboard
Users
Account Simulation
Tiers & Strategies
Market Prices
Deposits
Withdrawals
Transactions
Notifications
Audit Logs
Settings
```

## Dashboard

Show:

- registered users
- active users
- pending deposits
- pending withdrawals
- total platform managed balances
- recent activity
- recent admin actions

## Users

Admin can:

- search
- filter
- view account
- view balances
- view tier
- view strategy
- view P/L
- view performance
- view transactions
- view deposits
- view withdrawals
- view account status
- perform authorized account-management actions

## Account Simulation

Admin can control:

- managed balance
- pending balance where appropriate
- P/L
- performance %
- trading status
- performance snapshots

## Tiers & Strategies

Admin can configure:

- tier thresholds
- names
- strategy assignment
- descriptions
- benefits
- feature access
- display settings

## Market Prices

Admin can control:

- asset
- current simulated price
- 24h change
- active/inactive

## Deposits

Admin can:

- review
- confirm
- reject

## Withdrawals

Admin can:

- review
- approve
- reject

## Transactions

Global transaction history with filters.

## Audit Logs

Read-only audit history.

## Settings

Admin profile/security and basic platform configuration.

---

# 28. ADMIN PERMISSIONS

Keep this simple.

Two meaningful admin levels:

### Super Admin

Full administrative control.

### Admin

Operational management with restrictions around sensitive configuration.

Do not build an elaborate permission-management UI.

Use Laravel authorization policies/middleware.

If exact restrictions are not required by an existing feature, use sensible defaults and keep the implementation easy to expand.

---

# 29. PUBLIC WEBSITE

Create a polished public-facing marketing site.

Navigation:

```text
Logo
Home
How It Works
Strategies
Markets
FAQ
Login
Get Started
```

## Hero

Concise managed-trading positioning.

Primary CTA:

```text
Get Started
```

Secondary CTA:

```text
Explore Strategies
```

Include a subtle but visible simulation/demo disclosure.

## Trust / Platform Highlights

Examples:

- Managed Strategies
- Multi-Asset Access
- Transparent Account Monitoring
- Secure Account Management

## How It Works

Four steps:

```text
Create Account
↓
Fund Account
↓
Qualify for Strategy
↓
Monitor Account
```

## Strategies

Display:

```text
Foundation — $100+
Momentum — $1,000+
Elevation — $5,000+
Apex — $25,000+
```

Each should show:

- name
- minimum qualifying balance
- short positioning
- headline benefits
- View Details

View Details should expose:

- strategy description
- risk/strategy profile
- features
- benefits
- qualification requirement

Do not imply guaranteed financial results.

## Platform Features

Show:

- portfolio monitoring
- account performance
- transaction history
- deposits/withdrawals
- strategy information
- notifications
- secure account settings

## Markets

Show:

- BTC
- ETH
- LTC
- USDT

## Performance

Show a polished simulated performance visualization.

Clearly identify it as simulated/demo data.

## Testimonials

Testimonials may be used for the visual presentation.

If they are fictional/demo testimonials, clearly label them appropriately.

Do not present fictional testimonials as verified real customer statements.

## FAQ

Cover:

- managed trading
- account funding
- supported cryptocurrencies
- tier qualification
- withdrawals
- deposit confirmation
- performance
- demo/simulation nature

## Final CTA

Clear call to action.

## Footer

Include:

- brand
- navigation
- strategies
- markets
- FAQ
- contact
- terms
- privacy
- appropriate demo/risk disclosure

---

# 30. DESIGN DIRECTION

The application should feel like a modern premium fintech platform.

Desired characteristics:

- clean
- minimal
- sophisticated
- trustworthy visual language
- strong typography
- clear hierarchy
- polished cards
- modern charts
- subtle gradients/ambient effects
- smooth transitions
- restrained animations
- responsive layouts
- excellent spacing
- consistent component system

Use inspiration from modern fintech dashboards and the general structural conventions of the Drexa frontend reference.

Reference material:

```text
https://github.com/Hapis-Supremacy/drexa-frontend
https://drexa-frontend.vercel.app/
```

IMPORTANT:

Do NOT copy Drexa's source code, branding, assets, exact visual implementation, or create a cosmetic fork.

Use it only as inspiration for:

- information architecture
- general dashboard conventions
- component organization
- interaction patterns
- modern fintech presentation

The resulting product must have its own implementation, branding, content, structure, and visual identity.

---

# 31. RESPONSIVE DESIGN

The entire application must work properly across:

- desktop
- tablet
- mobile

Do not treat mobile as an afterthought.

Dashboard navigation should adapt intelligently to smaller screens.

Tables, charts, cards, forms, navigation, and modal interfaces must remain usable on mobile.

Avoid horizontal overflow wherever reasonably possible.

---

# 32. VISUAL DEVELOPMENT ORDER

Do NOT spend large amounts of time on animation before the core application works.

Use this order:

```text
Structure
↓
Functionality
↓
Data flow
↓
Responsive behavior
↓
Visual refinement
↓
Animations/micro-interactions
↓
Final polish
```

Later add:

- subtle card motion
- smooth transitions
- floating testimonial effects
- ambient background effects
- polished charts
- micro-interactions
- interactive states

Animations should support the interface, not distract from it.

---

# 33. EMPTY STATES / ERROR STATES

Do not leave blank screens when data does not exist.

Build sensible states for:

- no transactions
- no pending deposits
- no pending withdrawals
- no performance history
- no notifications
- suspended account
- rejected deposit
- rejected withdrawal
- loading
- validation errors
- server errors

Keep them clean and understandable.

---

# 34. DATABASE MODEL

Core tables:

```text
users
accounts
assets
asset_wallets
deposits
withdrawals
transactions
tiers
strategies
performance_snapshots
market_prices
audit_logs
notifications
```

Laravel's standard authentication/session-related tables may also be added as required.

Use migrations and foreign keys properly.

Use indexes for commonly searched/filterable fields.

Do not create unnecessary tables simply for theoretical future functionality.

---

# 35. DATA RELATIONSHIPS

Basic relationship model:

```text
User
 └── Account
      └── Tier
           └── Strategy

User
 ├── Deposits
 ├── Withdrawals
 ├── Transactions
 └── Notifications

Asset
 ├── Asset Wallets
 ├── Deposits
 ├── Withdrawals
 └── Market Price

Account
 └── Performance Snapshots

Admin
 └── Audit Logs
```

Keep relationships conventional Laravel Eloquent relationships.

---

# 36. API ARCHITECTURE

React communicates with Laravel through HTTP/API endpoints.

Organize endpoints logically.

Examples:

```text
/api/auth/...
/api/profile/...
/api/dashboard/...
/api/portfolio/...
/api/markets/...
/api/wallet/...
/api/deposits/...
/api/withdrawals/...
/api/transactions/...
/api/notifications/...
/api/admin/...
```

Do not expose database access directly to React.

Laravel owns:

- validation
- business logic
- authorization
- calculations
- database operations
- financial state

React owns:

- UI
- forms
- presentation
- client-side interaction
- API communication

---

# 37. VALIDATION

Validate on the backend even when the frontend validates.

Examples:

- deposit amount
- withdrawal amount
- asset
- wallet address
- transaction reference
- account permissions
- tier changes
- admin actions

Never assume client-side validation is sufficient.

---

# 38. TIER CALCULATION

Implement tier calculation centrally on the backend.

Conceptually:

```text
if managed_balance >= 25000
    Apex

else if managed_balance >= 5000
    Elevation

else if managed_balance >= 1000
    Momentum

else if managed_balance >= 100
    Foundation

else
    No qualifying tier
```

However, thresholds must come from the database/configuration rather than being duplicated throughout the application.

The frontend should display the result returned by the backend.

---

# 39. FINANCIAL OPERATION RULE

Any operation that changes financial/account state must be treated carefully.

Examples:

- approving deposits
- approving withdrawals
- rejecting withdrawals that release reservations
- account adjustments
- simulated balance changes

Use:

```text
database transaction
+
financial record
+
audit record
```

where appropriate.

Prevent partial updates.

---

# 40. DEMO DATA

Create realistic seed/demo data.

Seed:

- demo users
- admin accounts
- tiers
- strategies
- supported assets
- wallet addresses
- market prices
- performance snapshots
- sample deposits
- withdrawals
- transactions
- notifications

The seed data should make the application visually useful immediately after setup.

Clearly distinguish demo/simulated data where it matters.

---

# 41. NO REFERRAL SYSTEM

There is intentionally **NO referral system**.

Do not create:

- referral pages
- referral links
- referral codes
- referral commissions
- referral tables
- referral APIs
- referral UI
- referral navigation

Do not reintroduce referrals as an assumed feature.

---

# 42. BUILD PROCESS

Build in phases.

## Phase 1 — Foundation

Create:

- React/Vite frontend
- Laravel backend
- MySQL configuration
- project structure
- API foundation
- Sanctum foundation
- environment configuration

## Phase 2 — Authentication

Build:

- registration
- login
- logout
- email verification
- password reset
- profile
- security
- roles
- authorization

## Phase 3 — Database & Core Models

Build:

- users
- accounts
- assets
- asset wallets
- tiers
- strategies
- deposits
- withdrawals
- transactions
- performance snapshots
- market prices
- audit logs
- notifications

## Phase 4 — User Platform

Build:

- dashboard
- portfolio
- markets
- wallet
- transactions
- notifications
- settings

## Phase 5 — Admin Platform

Build:

- admin dashboard
- users
- account simulation
- deposits
- withdrawals
- transactions
- tiers/strategies
- market prices
- wallet addresses
- audit logs
- settings

## Phase 6 — Public Website

Build:

- homepage
- how it works
- strategies
- markets
- FAQ
- CTA
- testimonials
- footer/legal disclosure

## Phase 7 — Visual Refinement

Improve:

- typography
- spacing
- responsive layouts
- charts
- cards
- transitions
- animations
- ambient effects
- micro-interactions

## Phase 8 — Security & Consistency

Verify:

- authentication
- authorization
- validation
- CSRF
- rate limits
- balance calculations
- withdrawal reservations
- transaction consistency
- audit logs
- tier calculations
- edge cases

## Phase 9 — Demo Polish

Verify:

- seeded demo data
- user flows
- admin flows
- deposit flow
- withdrawal flow
- performance display
- market display
- responsive behavior
- visual consistency

---

# 43. DEVELOPMENT METHODOLOGY

Work using:

```text
BUILD
↓
RUN
↓
INSPECT
↓
FIX
↓
VERIFY
↓
CONTINUE
```

Do not attempt to build the entire application blindly in one pass.

After each major phase:

1. implement
2. run/test
3. inspect the result
4. fix issues
5. continue

Prefer working software over large amounts of speculative code.

---

# 44. IMPORTANT AGENT-CREDIT RULE

The user has limited coding-agent credits.

Therefore:

### Do NOT unnecessarily:

- reinstall dependencies
- recreate the project
- regenerate boilerplate
- repeatedly run setup commands
- restart development infrastructure unnecessarily
- install libraries that are not required
- rewrite working code
- run expensive commands without reason

The user will handle ordinary terminal commands manually whenever practical.

Your job is to use agent execution primarily for:

- implementation
- inspection
- reasoning
- debugging
- verification
- targeted modifications

If a terminal/setup command genuinely needs to be run by the user, tell them the exact command and briefly explain why.

Do not waste agent credits performing routine commands the user can easily run.

---

# 45. EXPLANATIONS

The user is experienced with React but new to PHP/Laravel.

When introducing an unfamiliar Laravel/PHP concept, explain it briefly in plain language.

Example:

> This is a Laravel migration. It defines the database table structure in code so the database can be created consistently.

Do not turn implementation into a long tutorial.

Explain only enough for the user to understand the decision and continue.

---

# 46. AVOID HALLUCINATIONS

Never claim something exists unless you have inspected it.

Before modifying an existing file:

- inspect it first
- understand its current structure
- then modify it

Do not assume:

- package versions
- file names
- routes
- installed dependencies
- environment variables
- database schema
- existing components
- Laravel configuration
- API behavior

If something is uncertain, inspect the project.

If inspection is impossible and the uncertainty materially affects implementation, ask.

---

# 47. KEEP THE ARCHITECTURE COMPACT

Do not over-engineer.

Avoid unnecessary:

- service layers
- repositories
- abstract factories
- event systems
- microservices
- complex state-management frameworks
- excessive database tables
- unnecessary APIs
- elaborate permission systems
- unnecessary dependencies

Use standard Laravel patterns first.

Introduce additional abstraction only when it solves an actual problem.

---

# 48. FRONTEND STATE

Do not introduce a large global state-management system unless the application genuinely requires it.

Prefer:

- local component state
- React hooks
- feature-level state
- server/API state handling appropriate to the actual complexity

Keep data ownership clear.

---

# 49. UI COMPONENTS

Build reusable components for repeated UI patterns.

Examples:

- buttons
- inputs
- selects
- cards
- modals
- badges
- tables
- stat cards
- chart containers
- alerts
- loading states
- empty states
- navigation
- sidebar
- mobile navigation

Avoid creating abstractions for components used only once unless there is a clear reason.

---

# 50. BRANDING

The application should have its own brand identity.

Do not use Drexa branding.

Use placeholder brand assets/name only where necessary until a final brand is supplied.

Keep branding easy to replace later.

---

# 51. LEGAL / DEMO PRESENTATION

Because the application uses simulated performance, make the distinction visible without making the UI ugly.

Use language such as:

```text
Demo Account · Simulated Performance
```

where appropriate.

Do not make claims of:

- guaranteed profit
- guaranteed returns
- guaranteed APY
- risk-free trading
- certain future performance

Do not fabricate claims about actual customers, trading results, licenses, regulation, or company history.

---

# 52. FINAL QUALITY STANDARD

The finished product should feel like a coherent real fintech application even though its financial/trading activity is simulated.

It should have:

- coherent navigation
- polished UI
- responsive design
- realistic demo data
- functioning authentication
- functioning user dashboard
- functioning admin dashboard
- functioning deposits
- functioning withdrawal workflow
- functioning transaction history
- functioning tier logic
- functioning performance snapshots
- functioning simulated markets
- proper authorization
- proper validation
- audit logging
- clear demo disclosure

The architecture must remain understandable to a developer who did not build it.

---

# 53. FIRST ACTION

Do NOT immediately generate the entire application.

First inspect the existing project/workspace.

Determine:

1. whether the React project already exists
2. whether the Laravel project already exists
3. current directory structure
4. installed dependencies
5. current configuration
6. whether MySQL/database configuration exists
7. what is already implemented

Then report a concise assessment.

After inspection, propose the **next single implementation step**.

Do not perform unnecessary setup if the required project foundation already exists.

Proceed incrementally from there.

The guiding principle is:

**Do the smallest correct step, verify it, then move to the next step.**
