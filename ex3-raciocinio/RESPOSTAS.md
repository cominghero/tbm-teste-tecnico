# Exercise 3: Reasoning

Four questions from the test document, answered in at most five lines each.

## Q1: A client reports the monthly attendance listing takes 12 seconds. Without looking at the code, what are your three starting hypotheses and why?

Likely in order: (1) no composite index on `(tenant_id, data)` or the equivalent month filter, which is the classic cause for a listing that gets slower as the table grows; (2) N+1 on the relations the listing joins (paciente, profissional, maybe notes), because Laravel hides this until you read the query log; (3) no pagination, so even a cheap query becomes slow once you serialize and ship 3000 rows of JSON. First move: slow query log or Telescope to see the actual SQL. If the query itself is fast, the problem is hidden behind it.

## Q2: You find three problems at once: hardcoded DB credential in the repo, CORS open to *, and 15 controllers with duplicated auth middleware. What's the fix order and why?

Credential first, CORS second, middleware duplication third. The credential may already be exploited right now; rotating it and scrubbing history is containment, not a feature, and it's the only item that could be silently leaking access this minute. Open CORS is attack-enabling but needs another vector (CSRF, phishing, malicious page) to matter. The middleware duplication is tech debt with no exploit path and no urgency. The rule I use: order by "is this being abused right now", not by "which one looks the worst in the codebase".

## Q3: You have two days to ship a new feature, and you find the relevant controller has 1100 lines of mixed logic. Do you refactor first, ship dirty and document the debt, or something else?

Something else: build the new feature in a separate controller next to the monster, not inside it. File the refactor as a ticket with concrete notes from this pass. Refactoring first burns the deadline. Shipping dirty inside the 1100-line file adds mass to a file that's already unmaintainable and makes the next person's job worse. A clean commit boundary lets me ship on time and leaves the legacy rot on the far side of a line I drew.

## Q4: The system uses `$fillable = ['*']` in 13 models. You need to fix it. What are the risks of doing all at once vs gradually? How do you decide?

All at once risks silent regressions: some caller somewhere was relying on being able to set a column that's now blocked, and the failure shows up in production, not in tests. Gradual risks a long exposure window plus coordination cost. I go gradual, but ordered: models touching sensitive columns first (users, any patient-facing data, anything with a `tenant_id`), one PR per model, each paired with a test pass through the real create/update controllers. Feature flags are overkill here because the blast radius is readable directly from the code.
