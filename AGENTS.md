# Agents.md

This file provides guidance to AI agents working on the SaaSykit project, including an overview of the project, development commands, architecture, coding standards, and environment setup.

## Project Overview

SaaSykit Tenancy is a  multi-tenant Laravel-based SaaS starter kit built with the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire). It provides a complete SaaS boilerplate with subscription management, payment processing, admin panels, and user dashboards powered by Filament.

SaaSykit Tenancy is a SaaS starter kit (boilerplate) that comes packed with all components required to run a modern SaaS software.

SaaSykit Tenancy is built with the TALL stack (Tailwind CSS, Alpine.js, Laravel, Livewire), and offers an intuitive Filament admin panel that houses all the pre-built components like product, plans, discounts, payment providers, email providers, transactions, blog, user & role management, and much more.

### Features in a nutshell

* Multi-tenancy (SaaSykit Tenancy): Build multi-tenancy applications, seat-based subscriptions with a seamless checkout experience.
* Customize Styles: Customize the styles & colors, error page of your application to fit your brand.
* Product, Plans & Pricing: Create and manage your products, plans, and pricing from a beautiful and easy-to-use admin panel.
* Beautiful checkout process: Your customers can subscribe to your plans from a beautiful checkout process.
* Huge list of ready-to-use components: Plans & Pricing, hero section, features section, testimonials, FAQ, Call to action, tab slider, and much more.
* User authentication: Comes with user authentication out of the box, whether classic email/password or social login (Google, Facebook, Twitter, Github, LinkedIn, and more).
* Discounts: Create and manage your discounts and reward your customers.
* SaaS metric stats: View your MRR, Churn rates, ARPU, and other SaaS metrics.
* Multiple payment providers: Stripe, Paddle, Lemon Squeezy and Offline (manual) payments support out of the box.
* Multiple email providers: Mailgun, Postmark, Amazon SES, and more coming soon.
* Blog: Create and manage your blog posts.
* User & Role Management: Create and manage your users and roles, and assign permissions to your users.
* Fully translatable: Translate your application to any language you want.
* Sitemap & SEO: Sitemap and SEO optimization out of the box.
* Admin Panel: Manage your SaaS application from a beautiful admin panel powered by Filament.
* User Dashboard: Your customers can manage their subscriptions, change payment method, upgrade plan, cancel subscription, and more from a beautiful user dashboard powered by Filament.
* User Onboarding: Guide your users through the onboarding process with a beautiful onboarding wizard.
* Two-factor authentication: Secure your users' accounts with two-factor authentication.
* ReCaptcha: Protect your application from spam and abuse with Google reCAPTCHA.
* Roadmap: Let your users suggest features and vote on them and keep them updated on what's coming next.
* Automated Tests: Comes with automated tests for critical components of the application.
* One-line deployment: Provision your server and deploy your application easily with integrated Deployer support.
* Developer-friendly: Built with developers in mind, uses best coding practices.
*

## Development Commands

### Frontend Development
- `npm run dev` - Start Vite development server for asset compilation
- `npm run build` - Build assets for production
- `public/build/` is not versioned (see `.gitignore`). Run `npm run build` once after cloning, and again before any frontend measurement or before using the content panel. Deployments build the assets via the Deployer task `npm:build`.

### Backend Development
- `php artisan serve` - Start Laravel development server
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan queue:work` - Start queue worker
- `php artisan horizon` - Start Laravel Horizon for queue monitoring

### Testing & Quality
- `vendor/bin/phpunit` - Run PHPUnit tests
- `vendor/bin/phpunit --filter=TestName` - Run specific test
- `vendor/bin/phpstan analyse` - Run static analysis (level 3)
- `vendor/bin/pint` - Run Laravel Pint code formatter
- `php scripts/secret-scan.php` - Secret-Scan über alle versionierten Dateien
  (auch als `composer run secrets:scan`; läuft zusätzlich im Pre-Commit-Hook und
  in der CI, siehe `docs/secret-scan.md`)

### Credentials
Zugangsschlüssel gehören ausschließlich in die lokale `.env`. Im Code werden sie
nur über `env('NAME')` **ohne Vorgabewert** gelesen — ein Literal als zweiter
Parameter ist ein eingecheckter Schlüssel und wird vom Secret-Scan abgelehnt.
Jede neue Variable gehört ohne Wert in `.env.example`.

### Deployment
- `php dep deploy` - Deploy using Deployer (configured in deploy.php)

## Architecture & Structure

### Core Directories
- `app/Filament/Admin/` - Admin panel resources and pages (Filament 4)
- `app/Filament/Dashboard/` - User dashboard resources (Filament 4)
- `app/Services/` - Business logic services (service layer pattern)
    - `PaymentProviders/` - Payment gateway implementations (Stripe, Paddle, Lemon Squeezy, Offline)
    - `VerificationProviders/` - User verification integrations
- `app/Models/` - Eloquent models with relationships
- `app/Livewire/` - Livewire components
    - `Auth/` - Authentication components
    - `Checkout/` - Checkout flow components
    - `Roadmap/` - Feature voting components
- `app/Http/` - Controllers and middleware
- `app/Notifications/` - Email/notification classes
- `app/Events/` - Domain events (Order, Subscription, User)
- `app/Listeners/` - Event listeners
- `app/Mail/` - Mailable classes (organized by domain)
- `app/Dto/` - Data Transfer Objects
- `app/Mapper/` - Data mappers
- `app/Constants/` - Application constants
- `app/Policies/` - Authorization policies
- `app/Validator/` - Custom validation rules
- `app/Console/Commands/` - Artisan commands
- `database/migrations/` - Database schema migrations
- `database/seeders/` - Database seeders
- `resources/views/` - Blade templates
- `resources/views/livewire/` - Livewire component views
- `tests/` - Automated tests (PHPUnit)

### Key Technologies
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Livewire 3 + Alpine.js + Tailwind CSS 4 + DaisyUI 5
- **Admin Interface**: Filament 4 with Spatie Media Library plugin
- **Asset Compilation**: Vite 7
- **Payments**: Stripe, Paddle, Lemon Squeezy, Offline (manual)
- **Queue System**: Laravel Horizon (Redis-based)
- **Authentication**:
    - Laravel Sanctum (API tokens)
    - Filament Breezy (auth UI)
    - Social login via Laravel Socialite (Google, Facebook, Twitter, GitHub, LinkedIn)
    - One-time passwords (Spatie)
    - Two-factor authentication (Laragear)
- **Email**: Supports Mailgun, Postmark, Amazon SES, Resend
- **SMS**: Twilio integration
- **Media**: Spatie Media Library + Intervention Image
- **Permissions**: Spatie Permission package (roles & permissions)
- **Testing**: PHPUnit, Static Analysis (PHPStan/Larastan level 3)
- **Code Quality**: Laravel Pint (PSR-12 formatting)
- **Debugging**: Laravel Telescope (dev), Laravel Debugbar (dev)
- **Deployment**: Deployer (automated deployment)

### Core Domain Models
Key models representing the business domain:
- **Tenant** - Tenants (for multi-tenancy)
- **User** - Users with roles, permissions, subscriptions
- **Product** - Products (subscription-based SaaS offerings)
- **Plan** - Subscription plans with pricing tiers
- **PlanPrice** - Pricing for plans (per interval/currency)
- **PlanMeter** - Usage-based billing meters
- **Subscription** - User subscriptions to plans
- **SubscriptionUsage** - Usage tracking for metered billing
- **OneTimeProduct** - One-time purchasable products
- **OneTimeProductPrice** - One-time product pricing
- **Order** - One-time product orders
- **Invoice** - Generated invoices (subscription/one-time)
- **Transaction** - Payment transactions
- **Discount** - Discount rules
- **DiscountCode** - Discount codes with redemption tracking
- **BlogPost** - Blog posts with categories
- **RoadmapItem** - Feature requests with user voting
- **Announcement** - User announcements
- **PaymentProvider** - Payment gateway configurations
- **EmailProvider** - Email service configurations
- **Currency** - Supported currencies
- **Address** - User/order addresses
- **Config** - Dynamic application configuration

### Service Layer
The application uses a service layer pattern. Key services:
- `TenantService` - Tenant management
- `TenantSubscriptionService` - Tenant subscription handling
- `TenantPermissionService` - Tenant roles/permissions
- `TenantCreationService` - Tenant onboarding
- `SubscriptionService` - Subscription lifecycle management
- `OrderService` - Order processing
- `CheckoutService` - Checkout flow logic
- `PlanService` - Plan management
- `DiscountService` - Discount application
- `InvoiceService` - Invoice generation
- `MetricsService` - SaaS metrics calculation (MRR, ARPU, churn)
- `TransactionService` - Transaction handling
- `UserService` - User management
- `LoginService` - Authentication logic
- `OneTimePasswordService` - OTP handling
- `BlogService` - Blog post management
- `RoadmapService` - Feature voting
- `CurrencyService` - Currency operations
- `ConfigService` - Dynamic configuration

### Payment Provider Architecture
Payment providers are abstracted via contracts in `app/Services/PaymentProviders/`:
- Each provider implements common interfaces
- Supports Stripe, Paddle, Lemon Squeezy, and Offline payments
- Provider-specific data stored in `*PaymentProviderData` models
- Webhooks handle provider callbacks

### Event-Driven Architecture
The application uses Laravel events for domain actions:
- **Order Events**: Order created, completed, failed
- **Subscription Events**: Created, updated, cancelled, renewed, trial started/ended
- **User Events**: Registered, verified, etc.
- Listeners handle side effects (emails, notifications, metrics)

## Coding Standards

### Primary Guidelines
When working on this Laravel/PHP project, **always** follow the coding guidelines at @ai/laravel-php-ai-guidelines.md. Key principles:
- Follow Laravel conventions first
- Use PSR-1, PSR-2, PSR-12 standards
- Use typed properties over docblocks
- Prefer early returns (happy path last)
- Use camelCase for variables/methods, PascalCase for classes
- Use kebab-case for routes, snake_case for config keys
- Avoid else statements when possible
- Use constructor property promotion
- String interpolation over concatenation
- Always use curly braces for control structures

### SaaSykit-Specific Conventions
- Services should be stateless and injected via dependency injection
- Use DTOs for complex data structures passed between layers
- Event/Listener pattern for side effects
- Filament for admin UI (avoid custom controllers when possible)
- Livewire for interactive frontend components
- Payment provider logic should be isolated in provider-specific classes
- All monetary amounts use the `Money` package
- Translations via `__()` function
- Use Spatie Permissions for authorization
- Queue long-running tasks (emails, webhooks, metrics)

### Database Conventions
- Use migrations for all schema changes
- Foreign keys with cascade/set null as appropriate
- Use proper indexes for performance
- Version history via `mpociot/versionable` where needed

### Testing Guidelines
- Feature tests for critical flows (subscription, checkout, payment)
- Unit tests for services with complex logic
- Use factories for test data
- Mock external services (payment providers, email)
- Run tests before committing: `vendor/bin/phpunit`

## Environment Setup

### First-Time Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
```

### Development Tools
- **Horizon Dashboard**: `/horizon` (queue monitoring)
- **Telescope Dashboard**: `/telescope` (debugging, dev only)
- **Admin Panel**: `/admin`
- **User Dashboard**: `/dashboard` 
