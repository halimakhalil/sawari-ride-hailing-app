# Sawari API (Laravel)

Backend for the Sawari ride-booking app. Setup, demo accounts, test commands and API examples are in the [root README](../README.md); design notes are in [ARCHITECTURE.md](../ARCHITECTURE.md).

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://127.0.0.1:8000  ·  API docs at /docs
php artisan test           # needs the ride_hailing_test database
```

Where things live:

| Path | Purpose |
|---|---|
| `app/Services/RideService.php` | Ride lifecycle, row locking, idempotency |
| `app/Services/DriverMatchingService.php` | Nearby drivers, ETA, driver feed |
| `app/Services/FareCalculator.php` | Fare formula |
| `app/Enums/RideStatus.php` | State machine |
| `database/migrations/` | Schema, including generated-column unique constraints |
| `public/openapi.yaml` | API contract |
| `tests/Concurrency/` | Multi-process race tests |
