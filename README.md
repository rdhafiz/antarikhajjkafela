# Antarik Hajj Kafela

Website and admin portal for **Antarik Hajj Kafela** (আন্তরিক হজ্জ কাফেলা), a Hajj & Umrah travel agency in Dhaka, Bangladesh.

- **Public site:** server-rendered Blade + Tailwind CSS, bilingual (English / Bangla), SEO-first.
- **Admin portal:** a Vue 3 SPA for content, packages, bookings and settings.
- **Booking:** with or without an advance payment, through SSLCommerz or DGePay.

See [docs/PLAN.md](docs/PLAN.md) for the full scope and architecture.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- MySQL 8

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

## Development

```sh
composer dev        # app server, logs and Vite
composer test       # run the test suite
vendor/bin/pint     # code style
```
