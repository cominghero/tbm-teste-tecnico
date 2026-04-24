# Exercise 2: Check-in feature

`POST /api/v1/checkin` for professionals checking in at a patient's home. Authenticated with Sanctum, strict multi-tenant isolation, covered by feature tests.

## Running the tests

```bash
composer install
php artisan test
```

The test DB is SQLite in-memory (set in `phpunit.xml`). Nothing else to configure.

## Shape of the endpoint

Request:

```
POST /api/v1/checkin
Authorization: Bearer <sanctum-token>

{
  "profissional_id": 42,
  "paciente_id": 17,
  "latitude": -29.91869,
  "longitude": -51.18094
}
```

Response (201):

```json
{
  "data": {
    "id": 1,
    "profissional": { "id": 42, "nome": "..." },
    "paciente":    { "id": 17, "nome": "..." },
    "latitude": -29.91869,
    "longitude": -51.18094,
    "checked_in_at": "2026-04-24T12:00:00+00:00"
  }
}
```

No `tenant_id` in the output on purpose.

## Decisions worth calling out

**Tenant comes from the authenticated user, never from the request.** No `X-Tenant-ID` header, no `tenant_id` in the payload, no tenant query param. The `BelongsToTenant` trait reads it from `auth()->user()->tenant_id` both when scoping reads (global scope) and when stamping it on insert (creating hook). That's the whole isolation story; the controller never mentions tenant at all.

**The exists rule does the cross-tenant check.** The `StoreCheckinRequest` uses `Rule::exists('profissionais', 'id')->where('tenant_id', $tenant)->where('ativo', true)` and the equivalent for pacientes. A record from another tenant fails the same way a missing record does: 422 with a generic message. I chose 422 over 404 because from the caller's perspective, the id they sent is invalid as far as they can see. The result is the same (no existence leak), but 422 is the standard Laravel validation response for bad input.

**`User` does not use the `BelongsToTenant` trait.** Login needs a global lookup (by email, before a tenant context exists), and applying the scope would break that. Every other tenant-scoped entity (Profissional, Paciente, Checkin) does use it.

**`checked_in_at` is set server-side.** The field is not accepted in the payload. Professionals are in the field on phones with drifting clocks; the timestamp we care about is when the server recorded the visit, not what the device reported.

**Portuguese domain language.** Tables, columns, and state names (`profissionais`, `pacientes`, `ativo`, `nome`) follow the domain the product speaks. Class names stay PascalCase English Laravel convention. Tables are explicit on the Portuguese-plural models (`protected $table = 'profissionais'`) because Laravel's pluralizer follows English rules.

**`Rule::exists` bypasses Eloquent's global scope.** The exists rule goes through the query builder, not Eloquent, so the `TenantScope` on the models is not automatically applied. That is why the rule has an explicit `->where('tenant_id', ...)`. If someone later moves this validation to Eloquent helpers, that where clause is free via the scope, but the current form is self-contained and easy to audit.

**Migrations use sequential timestamps.** `2024_01_01_000001` through `000006`. Artificial dates, but the ordering is deterministic which is what Laravel cares about. Easier to read than real timestamps that would appear to cluster arbitrarily.

## What I deliberately did not do

- **No Policy file for `Checkin`.** For a single `store` endpoint the validation layer already carries the authorization (tenant match + active professional). A Policy would be duplication. If this endpoint grew a `show`, `update`, or `destroy`, I would add one.
- **No explicit throttling.** The route sits in the `api` group which already has Laravel's default throttle. Tightening it is a config change, not a design change.
- **No audit log.** Ex1 called out LGPD audit logging for PHI access. Check-ins are not PHI reads, so I did not add a log channel for them. If the scope expanded to include listing or downloading patient records, I would add one (see `config/logging.php`, there is an `audit` channel already defined).
- **No rate limit per professional.** A single professional could spam check-ins. Easy to add via `throttle:10,1` on the route; skipped because it is not the property the test is measuring.
- **No soft-deletes.** Not asked for, no requirement to back them up. Real projects usually need them for clinical records.
- **No real authentication endpoint.** The test specifies the `/api/v1/checkin` route and feature tests. I did not build `/login` or token issuance; tests use `Sanctum::actingAs(...)`.

## File map

```
app/
  Http/
    Controllers/Api/V1/CheckinController.php
    Requests/Api/V1/StoreCheckinRequest.php
    Resources/CheckinResource.php
  Models/
    Concerns/BelongsToTenant.php    (trait: scope + creating hook)
    Scopes/TenantScope.php          (where tenant_id = auth user's)
    Tenant.php
    User.php                        (HasApiTokens; NOT using the trait)
    Profissional.php                (BelongsToTenant)
    Paciente.php                    (BelongsToTenant)
    Checkin.php                     (BelongsToTenant)
database/
  migrations/*                      (6 migrations, sequential timestamps)
  factories/*
routes/api.php                      (one route, auth:sanctum)
tests/Feature/CheckinTest.php       (6 tests)
```

## If I had another day

- Add a Policy once there are more endpoints on `Checkin`.
- Replace the token-based tests for `checked_in_at` with a controlled `CarbonImmutable::setTestNow()` freeze so assertions on the timestamp are not "whatever now is".
- A `BelongsToSameTenantAs` custom rule to DRY up the two `Rule::exists` blocks.
- Integration test that hits `/login` (issuing a real Sanctum token) instead of `actingAs`.
