---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# No raw identifiers: a pseudonym instead of `objectId`

## Context and Problem Statement

In Klantinteracties, a `partijIdentificator` carries the raw number in `partijIdentificator.objectId`, for example a
BSN. This register never receives raw numbers, only pseudonyms computed by the backend
([0002](0002-crypto-in-backend.md)). Where does the pseudonym go?

## Decision Drivers

- The register must never hold a plain BSN, RSIN, KvK number or vestigingsnummer.
- A client that knows Klantinteracties must not mistake a pseudonym for a real identifier.
- Naming follows Klantinteracties 1:1 wherever possible ([0003](0003-klantinteracties-naming.md)).

## Considered Options

1. Store the pseudonym in `objectId`
2. Leave `objectId` out and add a separate `pseudoniem` field, plus `sleutelversie`
3. Store an encrypted raw identifier in `objectId`

## Decision Outcome

Chosen option: **2, leave `objectId` out and store the pseudonym in its own `pseudoniem` field**, next to
`sleutelversie` (the HMAC key version). This is a deliberate deviation from Klantinteracties.

### Consequences

- Good, because the field name says what the value is, so nobody reads a pseudonym as a BSN.
- Good, because the register still holds no reversible identifier.
- Bad, because a client expecting `objectId` won't find it. The API documentation explains the deviation.
- Bad, because a migration to a real Klantinteracties API can only fill `objectId` through the backend, which can
  supply the raw number. Otherwise `objectId` stays empty there.

### Confirmation

The OpenAPI document has no `objectId` property and marks `pseudoniem` and `sleutelversie` as extensions.

## Pros and Cons of the Options

### 1. Pseudonym in `objectId`

- Good, because it would keep the Klantinteracties shape.
- Bad, because it is misleading: a Klantinteracties client would treat the value as a real BSN, and might for example
  send it to the BRP.

### 2. Separate `pseudoniem` field

- Good, because it is explicit and safe.
- Bad, because it is a deviation that must be documented and maintained.

### 3. Encrypted raw identifier in `objectId`

- Good, because a migration could decrypt it into the real `objectId`.
- Bad, because the register can't use it for lookups (encryption is randomised), so a pseudonym would still be needed.
- Bad, because it adds reversible identifiers to the register, which this register avoids.

## More Information

- The deviation is listed in the Klantinteracties mapping of the API documentation, together with the other
  extensions ([0003](0003-klantinteracties-naming.md)).
