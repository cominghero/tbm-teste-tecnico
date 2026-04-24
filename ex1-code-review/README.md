# Exercise 1: Code Review

`original/AtendimentoController.php` is the file from the test, copied as-is. My fixes for the top 3 critical issues land in `fixed/AtendimentoController.php` across the next three commits, one issue per commit.

Before the list: the system handles patient data, so I'm prioritizing by blast radius on clinical data and tenant boundaries, not by generic OWASP severity. A path traversal on a clinical file endpoint matters more here than in a blog.

## Critical

1. **SQL injection in `index()`**. `status`, `profissional`, dates and the tenant id are all concatenated into `whereRaw`. Any filter is a dump vector.
2. **Tenant comes from `X-Tenant-ID` header**. Client-controlled. Any authenticated user reads any tenant's data by setting the header. Every other fix depends on this being correct.
3. **Path traversal in `downloadEvolucao`**. `$fileName` and `$userId` flow straight into a filesystem path. `?fileName=../../../.env` walks out of storage.
4. **IDOR in `downloadEvolucao` and `update`**. Record id from URL, no ownership check. Combined with #2, anyone can mutate or read anything.
5. **Mass assignment in `store` and `update`**. `$request->all()` with no FormRequest. Client can set `tenant_id`, `token_acesso`, anything the model exposes.
6. **Arbitrary file upload**. No MIME check, no size limit, extension taken from the client. Filename built from `$request->input('nome')` without sanitizing, so path traversal at upload time too. RCE if the disk is web-accessible.
7. **Predictable access token**. `md5($profissional_id . time())`. Broken hash on guessable inputs, and it's used as the gate to a clinical record.

## High

- `Atendimento::find($id)` then `->update(...)` in `update()`. Returns null on missing id, 500s.
- No validation anywhere. No FormRequest, no date format, nothing.
- No Policy or Gate. Authorization is "is the user logged in".
- No pagination in `index()`. Homecare has thousands of attendances per tenant per month.
- `store()` is two writes (create + update for the token) with no transaction.
- `data_fim` is read without being checked as present, so if only `data_inicio` arrives the `BETWEEN` is broken.
- Middleware in the constructor calls `$this->auth()`, which doesn't exist on `Controller`. The closure throws or denies everyone. Dead code in a security-critical path.
- `redirect()->back()` from an endpoint that otherwise returns JSON. Breaks API clients.
- `$this->Pacientes = new Paciente()` holds model state on the controller. Not used either.
- Returns raw Eloquent models in JSON. Leaks `token_acesso`, timestamps, relations, whatever's in the schema.
- No rate limit on listing or upload.

## Medium

- `DB::table()` bypasses Eloquent global scopes. If a tenant scope is added later to `Atendimento`, this query ignores it.
- `$this->Pacientes` (PascalCase) violates PSR-12.
- `app/users/...` is nested under `Storage::disk('local')`, which already resolves to `storage/app`. The real path is `storage/app/app/users/...`.
- `file_get_contents()` on the upload loads the whole file into memory. Use `storeAs()`.
- `time()` as filename uniqueness. Collides under concurrent uploads.
- No audit log for PHI access. LGPD Art. 37 asks for this.
- `session('getMessage')` is a generic key that will collide.
- `LIKE '%...%'` on `profissionais.nome` forces a full scan.
- Response shapes are inconsistent (raw model vs. custom payload vs. filename-only).

## Which 3 I fixed, in this order

**1. Tenant spoofing (commit 7).** If the tenant id keeps coming from a header, none of the other fixes hold. Parameterizing SQL or adding a policy means nothing when the caller picks the tenant. This is the base of the multi-tenant model, so everything else follows from getting it right.

**2. SQL injection (commit 8).** Widest attack surface, cheapest fix. A single endpoint and you have the database. Swapping `whereRaw` for `->where(...)` is a mechanical change with a large payoff.

**3. Path traversal plus IDOR in `downloadEvolucao` (commit 9).** This is the endpoint that actually streams clinical evolution files. The two issues are solved by the same change: load the attendance through Eloquent with the tenant scope, authorize with a policy, and read the filename from the DB instead of the URL. One commit, two critical issues off the list.

The rest in priority order: mass assignment (#5) shrinks once tenant scoping is strict, the upload (#6) is a routine hardening pass, the token (#7) deserves a separate discussion about whether it should exist at all or be replaced by proper authorization. Given more time I'd also move `DB::table` to Eloquent and add the policies this controller is currently missing.
