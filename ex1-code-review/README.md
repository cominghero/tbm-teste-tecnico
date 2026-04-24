# Exercise 1: Code Review

The file [`original/AtendimentoController.php`](./original/AtendimentoController.php) is the controller that came with the test, copied verbatim. My rewrites for the top 3 critical issues will land in `fixed/` in the next three commits, one issue per commit, so the diff stays small.

Before getting to the code, a note on how I read this. The test says the controller is adapted from a real homecare system. That framing matters. The asset being protected is clinical data (PHI) tied to real patients, and we're operating under LGPD. So I'm not just ticking an OWASP checklist, I'm thinking about which failures would actually cause harm: leaking a patient's clinical evolution, letting tenant A read tenant B's charts, corrupting records through mass assignment, or giving away file system access through an upload. That's the lens I used to decide what's critical vs. just bad.

## Critical

Things that are exploitable today by an attacker (sometimes just a curious user) and lead directly to PHI leakage, tenant breach, or server compromise.

**C1. SQL injection in `index()`**
Every filter (`status`, `profissional`, `data_inicio`, `data_fim`) and the tenant id are concatenated into a string that's passed to `whereRaw`. A request with `?status=" OR "1"="1` dumps everything. Since the tenant id itself is also injected, there's not even a tenant boundary to fall back on. The only reason this doesn't already read like a breach post-mortem is that the attacker needs to know the endpoint exists.

**C2. Tenant spoofing via `X-Tenant-ID` header**
The tenant the request targets is taken from a client-controlled HTTP header. Any authenticated user can set that header to whatever they want and read another tenant's data. The right source is always `auth()->user()->tenant_id`. Client-supplied tenant ids are never trustworthy. This one is critical on its own, and it also turns every other endpoint's behavior into a cross-tenant problem.

**C3. Path traversal in `downloadEvolucao`**
`$filePath = 'app/users/' . $userId . '/' . $fileName`. Both segments come from the URL. `fileName=../../../../.env` and the server happily streams the `.env` file back. `userId` is similarly unchecked. Since this endpoint serves clinical evolution files, this is the straightest line from "authenticated user" to "arbitrary file read from the storage disk".

**C4. IDOR in `downloadEvolucao` and `update`**
Both endpoints take an id from the URL and never verify that the caller actually owns or is authorized to touch that record. `Atendimento::find($id)->update(...)` will happily update records from any tenant. Combined with C2, any logged-in user can walk the id space and mutate or read any record in the database.

**C5. Mass assignment in `store` and `update`**
`Atendimento::create($request->all())` and `->update($request->all())` with no FormRequest and no fillable discipline. The client gets to set `tenant_id`, `status`, `token_acesso`, whatever the model exposes. A malicious payload can flip an attendance into a different tenant or set a known token so the attacker can come back later and access the record.

**C6. Arbitrary file upload (`uploadImagem`)**
No MIME check, no size limit, and the extension comes from `getClientOriginalExtension()` which is client-controlled. Upload `shell.php.jpg`, or just `shell.php` with `nome=shell`, and it's on disk. Worse, the filename itself is built from `$request->input('nome')`, which means `nome=../../../../public/shell` traverses out of the uploads directory. If the storage disk points anywhere web-accessible, this is RCE.

**C7. Predictable access token**
`md5($profissional_id . time())`. MD5 is broken, the inputs are guessable, and the timestamp has one-second resolution. Anyone who knows roughly when an attendance was created and can enumerate professional ids can brute force the token offline. This token is the thing that gates access to the attendance, so it's effectively a password, and we're generating passwords with `md5` of known values.

## High

Not exploitable in the "send a single request and win" sense, but they make the system fragile, hide other bugs, or enable the critical ones.

**H1. `find()` without `findOrFail()` in `update`**
Returns null when the id doesn't exist. Next line does `->update(...)` on null, which 500s. That's a reliability bug and also a weak information channel about record existence.

**H2. No FormRequest or validation anywhere**
No typing of inputs, no format checks on dates, no required/forbidden rules. Makes C5 easier and leaves garbage data to rot in the database.

**H3. No authorization layer**
No Policy, no Gate, no ownership check. Every authenticated user can do anything to anything. This is the root cause of C4.

**H4. `index()` has no pagination**
Home care systems accumulate hundreds of attendances per professional per month. Loading all of them into memory, JSON-encoding them, and shipping them over the wire is the reason endpoints like this end up taking 12 seconds (see exercise 3, question 1).

**H5. `store` isn't atomic**
Creates the record, then runs a second query to update the token. If the second fails, you have a half-created attendance with no token. Generate the token before `create`, or wrap the whole thing in a transaction.

**H6. `data_fim` is read without `has()`**
If the client sends `data_inicio` but not `data_fim`, the query becomes `BETWEEN "..." AND ""`, which is either a parse error or a silently wrong result set. The filter pair should be validated together.

**H7. Duplicated middleware and a method that doesn't exist**
`$this->middleware('auth')` and then a second middleware that calls `$this->auth()`. Controllers don't have an `auth()` method, so that branch always throws, which means the closure effectively denies everyone or errors out. It looks like someone copy-pasted code from a context that had an `auth()` helper and never tested it.

**H8. `redirect()->back()` from a JSON endpoint**
The controller returns JSON everywhere else, but the middleware falls back to `redirect()->back()` on auth failure. That breaks any API client. Pick one: JSON response or web redirect. An API should respond `401 Unauthorized` in JSON.

**H9. Controller holds model state**
`$this->Pacientes = new Paciente()` in the constructor. Controllers are resolved per request, but storing a model instance as a property is still the wrong shape. And nothing in the shown code even uses it.

**H10. Raw model in JSON response**
`response()->json($atendimento)` leaks every column, including `token_acesso`, soft-delete fields, internal timestamps, any eager-loaded relations. An API Resource is the right place to decide what goes out.

**H11. No rate limiting**
Upload and listing endpoints are both exposed without throttling.

## Medium

Quality and maintainability issues. They don't bite you today, but they compound and make every later change harder.

**M1. `DB::table()` instead of Eloquent in `index()`**
Bypasses global scopes, observers, casts, and any model-level tenancy logic. If you later add a `BelongsToTenant` scope to `Atendimento`, this query silently ignores it. Reach for Eloquent by default, drop to the query builder only when there's a real reason.

**M2. `$this->Pacientes` property name**
PascalCase for a property violates PSR-12 and Laravel convention. Small thing but it's the kind of marker that says "no code review happened".

**M3. Redundant `app/` prefix in storage paths**
`Storage::disk('local')` already resolves to `storage/app`. Writing `app/users/...` on top of that creates `storage/app/app/users/...`. The code works but the paths are wrong in a way that confuses anyone trying to find the files on disk.

**M4. `file_get_contents()` on the uploaded file**
Reads the entire upload into memory before writing it. For a clinician uploading a 20 MB image from a field tablet, that's fine. For anything larger or a concurrent burst, it's a memory footgun. `->store()` or `->storeAs()` streams.

**M5. `time()` as the filename uniqueness source**
Two concurrent uploads in the same second produce the same filename. At low volume it's theoretical, but it's trivial to just use `Str::random(40)` or a UUID.

**M6. No audit log for PHI access**
LGPD (Art. 37) requires being able to show who accessed what medical data and when. None of these endpoints log the access. This is compliance-relevant, but listed as medium here because it doesn't cause harm on its own, it fails you during an audit.

**M7. `session('getMessage')` as a flash key**
Generic name that collides with anything else in the app using `getMessage`. Namespace it or use Laravel's flash helpers properly.

**M8. `LIKE '%...%'` on `profissionais.nome`**
Leading-wildcard LIKE forces a full table scan. Fine for 200 professionals, slow for 20k. A full-text index or prefix search fixes it.

**M9. Inconsistent response envelope**
Some responses return raw models, some return the upload filename, none use a resource. Pick a shape and stick with it.

## Prioritization: which 3 I fixed and why that order

The test asks for fixes to the top 3. I picked **C2 (tenant spoofing), C1 (SQL injection), and C3+C4 together (path traversal + IDOR in `downloadEvolucao`)**, in that order. Rationale:

**1. C2 first, because every other fix depends on it.** If the tenant id keeps coming from a client header, no amount of SQL parameterization or authorization logic protects you. The attacker just sets `X-Tenant-ID: some-other-tenant` and walks around all of it. Fixing tenant spoofing means the tenant id becomes a trustworthy constant derived from the authenticated user, which is the foundation every other piece of the authorization model stands on. Without this foundation, all the other fixes are theater.

**2. C1 second, because it's the broadest attack surface.** SQL injection is a universal primitive: it gives you read, write, and (depending on the driver) remote code execution. It doesn't require knowing any other endpoint or any other vulnerability. Anyone who finds this one endpoint and understands `UNION SELECT` has the whole database. Fixing it is cheap (swap `whereRaw` for parameterized `where`) and the payoff is high.

**3. C3 + C4 third, because they're how the PHI actually leaks.** Tenant spoofing and SQL injection are the big structural holes, but `downloadEvolucao` is the endpoint that literally streams clinical evolution files back to the client. Path traversal plus IDOR on a file-download endpoint for clinical records is the scenario you don't want to explain to a regulator. I bundled the two because the fix is one piece of code: look the attendance up through Eloquent with the tenant scope, authorize via a policy, then serve a file whose name came from the database, not the URL.

Why not C5, C6, or C7 in the top 3? They're serious, but each has either a narrower attack surface or is partially mitigated by fixing the three above. Mass assignment (C5) stops mattering as much once tenant scoping is strict, because the attacker can no longer move records across tenants even if they overwrite `tenant_id`. The upload (C6) is brutal but it's one endpoint and the fix is routine (validate MIME, sanitize the filename, use `storeAs` with a generated name). The token (C7) is also serious but it's a smaller blast radius, one record per token, and it needs a separate discussion about whether the token should exist at all or be replaced by a proper authorization check.

What I'd fix in the next pass, in priority order: C5, C6, C7, H1-H5, everything else. If I had another day I'd also propose removing the custom token in favor of Sanctum or a policy, since it's essentially a homegrown auth scheme on top of the real one.
