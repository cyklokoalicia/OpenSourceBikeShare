# Ordinary rental ledger core (0013, step 2)

`NormalRentalWriter` implements the first persistence slice, on top of the staged
schema in step 1. It is intentionally not wired into `AbstractRentSystem`, controllers,
or SMS commands. Merging it does not activate the new writer on an installation.

A rent returns its inserted `rentId`. A return requires that exact `expectedRentId`
and inserts `RETURN.pairActionId = RENT.id`. The start is never updated. Both events
store the observed station using the existing standId field. No columns are added.
The returned IDs are history row IDs, not additional link columns or a trips table.

The pure planner rejects an unavailable bike, mismatched holder/projection, multiple
open starts, stale rental ID or a return before its start.
The repository determines active starts through incoming terminal links within the
new history range. `RENTAL_LEDGER_START_ID` is the fixed maximum history.id captured
while all old rental writers are paused/drained at cutover. Both starts and terminals
in the lookup must have IDs above it; old dangling/reverse links cannot masquerade as
new closures. The default 0 is for fresh databases, not an automatic legacy migration.
Never recalculate or advance the boundary on restart or after backfill.

Historical backfill can run after new writes begin, confined to the old range. It
updates existing pairActionId values without adding columns or replaying commands.
Pairing invariants are enforced entirely by application code, both before and after
historical backfill. The database has ordinary lookup indexes, transactions and row
locks; it has no pair FK, UNIQUE, CHECK, trigger or stored procedure. Every rental
write must use the common writer. Historical backfill validates its own input in code.

Rentals active at cutover require separate reconciliation before their bikes can use
this core. A held bike whose opening is outside the new range is rejected; the core
does not manufacture a start or guess a legacy match. The activation/migration stage
must define their transition before enabling the writer for those bikes. Completing
all old historical pairs is not a prerequisite for starting new rentals on reconciled
parked bikes. Appending reconstructed historical lifecycle events into the new ID
range is not supported by this backfill contract.

The writer owns one transaction and locks user, bike, then station. History queries
use current reads after the bike lock. State updates and the terminal insert commit
together. Concurrent starts/closures serialize, then revalidate. A repeated closure
is a conflict; this does not yet implement durable command receipts or replay results.

An optional `localEffects(RentalWriteResult)` callback runs after persistence, before
commit, on the same injected DB connection. It can validate operation policy against
the locked state and apply local credit/note writes; any exception rolls everything
back. `transition.startedAt` identifies the exact opening time for later pricing
integration. Callbacks must account for the already-applied transition (for example,
a rental count already includes this rent). They must not nest transactions, execute
DDL, or send external messages. Successful return from the writer is the commit
boundary; callers publish notifications afterwards.

This is a persistence component, not a replacement for the existing rental engine:
authorization, credit eligibility, user limits, stack policy, pricing/bonuses, notes,
and notification delivery must be integrated and tested in the operation layer before
any transport uses it. Existing application tests continue to cover the legacy path.
The integration tests exercise the new core with the real MariaDB schema, separate
connections, exact pairs, stale requests and injected write/effect failures.

Next slices add forced handover/relocation and REVERT, then all readers and transport
adapters under a coordinated activation switch. Active-state reconciliation and the
fixed cutover boundary are prerequisites for production activation; historical backfill can follow. Never
run this writer alongside legacy writers on the same bikes. There is no production
feature flag in this PR that could accidentally enable that mixed mode.

## Ownership of pairing rules

The repository reads candidate starts under the bike lock. The planner validates
availability, candidate count, exact expected ID, action, bike, holder, pair direction
and time. The writer persists a validated transition within the same transaction.
The second concurrent command reads state again after acquiring the lock and rejects
an already completed rental in PHP; it does not rely on a duplicate-key error.
SQL updates additionally check the expected projection and affected-row count.
