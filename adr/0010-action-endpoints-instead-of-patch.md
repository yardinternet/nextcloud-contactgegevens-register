---
status: accepted
date: 2026-11-26
decision-makers: Marc Schwartz
---

# One endpoint per transition instead of a general update

## Context and Problem Statement

A contactgegeven changes in only a few ways: it gets verified, it becomes the primary one, or it is forgotten
([0007](0007-verification-derived-from-date.md)). Some changes must never happen: a verified contactgegeven doesn't
become unverified again, and a primary one only stops being primary when another takes its place. How does the API let
the Mijn Omgeving change a contactgegeven?

## Decision Drivers

- The API should allow only the changes the register supports.
- At most one contactgegeven per identiteit and `soort` is primary ([0003](0003-own-model-and-api.md)).
- Every change gets an audit entry that says what happened ([0008](0008-audit-by-identiteit-uuid.md)).
- The register must stay small.

## Considered Options

1. `PATCH /contactgegevens/{uuid}` with `waarde`, `geverifieerdOp` and `isPrimair`
2. One endpoint per supported transition
3. `PUT /contactgegevens/{uuid}` that replaces the whole contactgegeven

## Decision Outcome

Chosen option: **2, one endpoint per supported transition.** The API has no `PATCH` and no `PUT`.

| Endpoint | Effect |
|---|---|
| `POST /contactgegevens/{uuid}/verifieer` | Sets `geverifieerd_op` to the current date. |
| `POST /contactgegevens/{uuid}/primair` | Makes it the primary one and ends that of the current primary one, in one transaction. |

Further rules:

- Neither endpoint takes a request body, and both can be repeated safely. Verifying a contactgegeven that is already
  verified keeps its date.
- The register sets the verification date. The Mijn Omgeving can't choose or clear it.
- A contactgegeven doesn't have to be verified to become primary.
- The first contactgegeven of a `soort` that is added to an identiteit becomes primary automatically. Every later one
  starts as not primary.
- A `waarde` can't be changed. To replace an address, the Mijn Omgeving deletes the contactgegeven and adds a new one,
  which starts unverified and has a new UUID.
- An identiteit has no update endpoint either. Its `hmac` only changes through a re-key
  ([0009](0009-key-rotation-overlap-window.md)).
- The path segments are Dutch, like the properties they set (`geverifieerdOp`, `isPrimair`).

### Consequences

- Good, because the API can't express a change that the register doesn't support, so there are no combinations of
  fields to validate.
- Good, because each endpoint maps to one audit `actie`.
- Good, because making one contactgegeven primary and ending that of another is one request, so there is never a
  moment with two primary ones.
- Bad, because correcting a typo in an address costs a delete and an add, and the address has to be verified again.
- Bad, because a new kind of transition needs a new endpoint.
- Neutral, because verbs in paths are not resource-style REST.

### Confirmation

The OpenAPI document contains no `PATCH` or `PUT` operation. Tests show that no endpoint clears `geverifieerdOp`, that
an identiteit never has two primary contactgegevens of one `soort`, and that the first contactgegeven of a `soort`
becomes primary.

## Pros and Cons of the Options

### 1. `PATCH` with the changeable fields

- Good, because it is one endpoint in a familiar style.
- Bad, because it gives the client more freedom than the register supports: clearing or backdating `geverifieerdOp`,
  or ending a primary without naming a new one. Each of those needs a validation rule and a test.
- Bad, because one request can combine changes, such as a new `waarde` together with a `geverifieerdOp`, and each
  combination needs a rule.

### 2. One endpoint per transition

- Good, because what isn't supported doesn't exist in the API.
- Bad, because the number of endpoints grows with the number of transitions.

### 3. `PUT` of the whole contactgegeven

- Good, because the request is the complete new state.
- Bad, because it has the same freedom as option 1, and the client also has to send the fields it doesn't change.
