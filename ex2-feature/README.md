# Exercise 2: Check-in feature

`POST /api/v1/checkin` for professionals in the field. Auth via Sanctum, strict multi-tenant isolation, feature tests on both the happy path and the cross-tenant attempt.

The content in this folder is a minimal Laravel 10 project. If anything looks missing vs. a full `composer create-project`, it's because I only kept what the feature and its tests actually need.

## Running the tests

```bash
composer install
php artisan test
```

The test database is SQLite in-memory (configured in `phpunit.xml`), so no database setup is needed.

## Layout

Filled in across commits 11 through 22. The shape at the end:

- `app/Models/`: `Tenant`, `User`, `Profissional`, `Paciente`, `Checkin`.
- `app/Models/Concerns/BelongsToTenant.php`: trait that binds every Eloquent model to the authenticated user's tenant, with a global scope that enforces it on reads.
- `app/Http/Controllers/Api/V1/CheckinController.php`
- `app/Http/Requests/Api/V1/StoreCheckinRequest.php`
- `app/Http/Resources/CheckinResource.php`
- `database/migrations/`
- `database/factories/`
- `routes/api.php`
- `tests/Feature/CheckinTest.php`

Detailed decisions (why global scope vs. explicit where, why 404 vs. 403 on cross-tenant, tradeoffs I took) go into this README in commit 22, once the code is all in.
