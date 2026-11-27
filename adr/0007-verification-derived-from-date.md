---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# Verification status derived from `geverifieerdOp`; forgetting is a delete

## Context and Problem Statement

The Mijn Omgeving only loads a party's agenda when the party has a verified primary email address. It runs the
verification itself, and how it does that is not the register's concern. The register only has to let the Mijn
Omgeving record that an email address is verified, and let it forget an email address. A forgotten address must
disappear immediately.

How does the register represent the state of an email address?

## Decision Drivers

- The model holds only what the Mijn Omgeving needs ([0003](0003-own-model-and-api.md)).
- A forgotten address must never be returned again, by any endpoint.
- The verification process can change in the Mijn Omgeving without a change in the register.

## Considered Options

1. A `status` column (unverified, verified, forgotten)
2. Derive the status from `geverifieerdOp`; forgetting deletes the row and writes an audit entry
3. Derive verified from `geverifieerdOp`; forgetting sets a soft-delete flag
4. A verification entity in the register that holds the token or code

## Decision Outcome

Chosen option: **2.**

| State | Register | Set by the Mijn Omgeving with |
|---|---|---|
| Not verified | `geverifieerd_op` is NULL | `POST /identiteiten/{uuid}/contactgegevens` |
| Verified | `geverifieerd_op` is set | `POST /contactgegevens/{uuid}/verifieer` |
| Forgotten | Row deleted, with an audit entry | `DELETE /contactgegevens/{uuid}` |

Further rules:

- The register stores nothing about the verification process: no token, code, expiry or number of attempts.
- Verification can't be undone, and a `waarde` can't be changed. To replace an address, the Mijn Omgeving deletes it
  and adds the new one, which starts unverified ([0010](0010-action-endpoints-instead-of-patch.md)).
- `geverifieerd_op` is a date, set by the register to the day of the request. The exact time of verification is in the
  audit log.
- The `isGeverifieerd` filter on the list of addresses is derived from `geverifieerd_op`.
- Deleting the primary address leaves the identiteit without one. The register doesn't pick a new one; the Mijn Omgeving
  makes another address primary with `POST /contactgegevens/{uuid}/primair`.
- Deleting the last address leaves the identiteit in place, without contactgegevens
  ([0011](0011-identiteit-without-contactgegevens.md)).

### Consequences

- Good, because one field says whether an address is verified, so the status can't contradict the date.
- Good, because a deleted row can't leak into any query, so a forgotten address is gone immediately.
- Good, because the Mijn Omgeving can verify with a code, a link or something else without the register changing.
- Bad, because the register trusts the Mijn Omgeving that an address it marks as verified really is.
- Bad, because the history of an address exists only in the audit log, which holds no address data
  ([0008](0008-audit-by-identiteit-uuid.md)). The register can't tell that an address was once verified and then
  forgotten.
- Bad, because re-adding a forgotten address starts unverified, with a new UUID.

### Confirmation

Tests show that a deleted address is not returned by any endpoint, and that no endpoint changes or clears
`geverifieerdOp` once it is set.

## Pros and Cons of the Options

### 1. Status column

- Good, because the states are explicit.
- Bad, because it duplicates what `geverifieerdOp` already says, so the two can contradict each other.
- Bad, because every query must remember to exclude forgotten addresses. One missed filter leaks one.

### 2. Derived status, forgetting is a delete

- Good, because it needs no extra column and makes the guarantee structural.
- Bad, because there is no history beyond the audit log.

### 3. Derived status, soft delete

- Good, because history is kept.
- Bad, because, as with option 1, every query must filter out deleted rows.
- Bad, because email addresses that were forgotten stay in the database.

### 4. Verification entity in the register

- Good, because the register could check a token itself instead of trusting the caller.
- Bad, because it ties the register to one way of verifying, while that choice belongs to the Mijn Omgeving.
- Bad, because it adds an entity, endpoints and token rules that the register doesn't need.
