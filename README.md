# Technical test TBM Home Care

Submission for the Full Stack Developer (Laravel + React) position. Three exercises, one folder each.

## Navigate

- [`ex1-code-review/`](./ex1-code-review): review of the `AtendimentoController` the test shipped with. Full list of problems by severity, prioritization rationale, and rewrites for the top three critical issues under `ex1-code-review/fixed/`.
- [`ex2-feature/`](./ex2-feature): the `POST /api/v1/checkin` endpoint. Sanctum auth, strict multi-tenant isolation via a `BelongsToTenant` trait with a global scope, six feature tests covering the happy path and the "fails the right way" cases.
- [`ex3-raciocinio/`](./ex3-raciocinio): short answers to the four reasoning questions.

Each folder has its own README with the decisions for that exercise. This one is the executive summary.

## What I prioritized

The thread through all three exercises is the multi-tenant boundary. The test frames the system as a real homecare product with patient data and LGPD exposure, which makes tenant isolation the highest-value property to protect.

- In ex1, tenant spoofing is ranked above SQL injection in the prioritization. Without a trustworthy tenant id, every other protection is decoration.
- In ex2, all tenant enforcement lives in one place (a trait + a global scope + a `creating` hook). No controller has to remember to scope anything. "Did you add the tenant filter" is a dead question by design.
- In ex3, the same reflex: contain first, order by exploitability, refactor later.

## Tradeoffs I took

- **Hand-wrote the Laravel scaffold in ex2** instead of generating it with `composer create-project`. Faster to iterate on commits, but more chance of a missing config line biting you when you `composer install`. If startup errors, the safe path is to delete `ex2-feature/`, run `composer create-project laravel/laravel:^10 ex2-feature`, and replay the custom code on top.
- **`Rule::exists` with an inline tenant `where`** instead of a reusable `BelongsToSameTenantAs` rule. Compact, but it doesn't DRY across the two fields. Noted in the ex2 README as the first thing I'd refactor.
- **422 on cross-tenant misses, not 404.** Same non-leak property, more idiomatic Laravel. Rationale in the ex2 README.
- **English for the docs, Portuguese for the domain.** Table and column names (`profissionais`, `pacientes`, `nome`, `ativo`) follow the product's vocabulary. Class names stay English Laravel convention. Written prose (commits, READMEs) is English.

## What the repo is not

- Not a full deployable Laravel app. ex2 is wired for the feature and its tests, not for `artisan serve`. No `/login` endpoint, no rate-limit tuning, no audit channel for the check-in endpoint.
- Not a React project. The job mentions React, the test didn't ask for it, I didn't build it.
- No CI. Running the tests is `composer install && php artisan test` inside `ex2-feature`.

## If I had another day

- A `BelongsToSameTenantAs` validation rule to replace the two `Rule::exists` blocks in `StoreCheckinRequest`.
- `CarbonImmutable::setTestNow()` around the happy-path test so the `checked_in_at` assertion becomes exact.
- An ex1 `fixed/` counterpart for `uploadImagem`. It's a top-3 candidate for the next pass and the fix is short.
- Move `DB::table()` in the ex1 fixed controller over to Eloquent with the same tenant scope pattern as ex2.

## Commit history

The commit log is the delivery narrative. Each commit is one reviewable unit: a section of the review, a single fix, a layer of the feature, one set of tests. A reviewer running `git log --oneline` should see the argument of the whole submission before opening any file.
