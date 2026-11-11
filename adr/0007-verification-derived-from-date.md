---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# Verification status derived from `verificatieDatum`; withdrawal is a delete

## Context and Problem Statement

A contact detail goes through three states: `onbevestigd` → `geverifieerd` → `ingetrokken`. Only verified
addresses may reach the afspraken-API, and a withdrawn address must disappear from it immediately. Klantinteracties
has no status field on `digitaalAdres`. It has a `verificatieDatum` and derives "verified" from it (its API even
offers an `isGeverifieerd` filter). How does the register represent these states?

## Decision Drivers

- Naming follows Klantinteracties 1:1 ([0003](0003-klantinteracties-naming.md)).
- A withdrawn address must never be returned again, by any endpoint.
- An unverified address must never give access to data.

## Considered Options

1. A `status` column with the three states
2. Derive the status from `verificatieDatum`; withdrawal deletes the row and writes an audit entry
3. Derive verified from `verificatieDatum`; withdrawal sets a soft-delete flag

## Decision Outcome

Chosen option: **2.**

| State | Register |
|---|---|
| `onbevestigd` | `verificatie_datum` is NULL |
| `geverifieerd` | `verificatie_datum` is set |
| `ingetrokken` | Row deleted, with an audit entry |

Further rules:

- Changing an address's `adres` clears `verificatie_datum`.
- `verificatie_datum` is a date, as in Klantinteracties. The exact time of verification is in the audit log.
- Deleting an address also deletes its open verification token (foreign key cascade). If it was the partij's
  preferred address, `voorkeursDigitaalAdres` becomes NULL and the app clears or re-evaluates `voorkeurskanaal`.

### Consequences

- Good, because the model matches Klantinteracties, including the `isGeverifieerd` filter.
- Good, because a deleted row can't leak into any query, so a withdrawn address leaves the afspraken-API immediately.
- Good, because the register holds no encrypted addresses that are no longer in use.
- Bad, because the history of an address exists only in the audit log, which holds no address data
  ([0008](0008-audit-by-partij-uuid.md)). The register can't tell that an address was once verified and then
  withdrawn.
- Bad, because re-adding a withdrawn address starts unverified, with a new UUID.

### Confirmation

Tests show that a deleted address is not returned by any endpoint, and that changing `adres` clears
`verificatieDatum`.

## Pros and Cons of the Options

### 1. Status column

- Good, because the states are explicit.
- Bad, because it deviates from Klantinteracties and duplicates what `verificatieDatum` already says.
- Bad, because every query must remember to exclude `ingetrokken`. One forgotten filter leaks a withdrawn address.

### 2. Derived status, withdrawal is a delete

- Good, because it follows the standard and makes the withdrawal guarantee structural.
- Bad, because there is no history beyond the audit log.

### 3. Derived status, soft delete

- Good, because history is kept.
- Bad, because, as with option 1, every query must filter out deleted rows.
- Bad, because encrypted addresses that were withdrawn stay in the database.
