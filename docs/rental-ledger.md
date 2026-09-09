# Ordinary rental ledger core (0013, step 2)

`NormalRentalWriter` implements the first persistence slice, on top of the staged
schema in step 1. It is intentionally not wired into `AbstractRentSystem`, controllers,
or SMS commands. Merging it does not activate version 1 writes on an installation.

A rent returns its inserted `rentId`. A return requires that exact `expectedRentId`
and inserts `RETURN.pairActionId = RENT.id`. The start is never updated. Both events
store the observed station, command origin, rental kind and verified ledger version.
The returned IDs are history row IDs, not additional link columns or a trips table.

The pure planner rejects an unavailable bike, mismatched holder/projection, multiple
open starts, stale rental ID, unsupported episode kind or a return before its start.
The repository determines active starts through incoming verified terminal links;
unverified old starts never become active just because they have no closing row.
A rented bike without a verified start needs reconciliation, not a guessed match.

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
adapters under a coordinated activation switch. Existing history normalization and
final constraint installation remain prerequisites for production activation. Never
run this writer alongside legacy writers on the same bikes. There is no production
feature flag in this PR that could accidentally enable that mixed mode.
