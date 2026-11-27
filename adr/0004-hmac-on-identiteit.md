---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# One HMAC per identiteit, stored on the identiteit

## Context and Problem Statement

The register looks up an identiteit by the HMAC that the Mijn Omgeving computes from a login identifier
([0002](0002-hmac-by-mijn-omgeving.md)). A login can yield more than one identifier: eHerkenning can supply a KvK
number and an RSIN for the same organisation. Where is the HMAC stored, and how many can an identiteit have?

## Decision Drivers

- The register must stay small ([0003](0003-own-model-and-api.md)).
- A lookup should need only the HMAC.
- Audit entries need a reference to the identiteit that stays the same over time
  ([0008](0008-audit-by-identiteit-uuid.md)).
- A key rotation in the Mijn Omgeving changes every HMAC.

## Considered Options

1. One `hmac` column on `identiteit`
2. A separate identifier record, so an identiteit can have several HMACs
3. The HMAC is the key of the identiteit; there is no UUID

## Decision Outcome

Chosen option: **1, one `hmac` column on `identiteit`.** An identiteit has exactly one HMAC. The Mijn Omgeving decides
which identifier of a login it hashes, and uses the same one every time.

A key rotation replaces the `hmac` of an identiteit when its party logs in; the `uuid` stays the same
([0009](0009-key-rotation-overlap-window.md)). The register keeps no key version, only the moment at which the
`hmac` was last set (`hmac_gezet_op`).

### Consequences

- Good, because there is one table, one mapper and one resource fewer than with a separate identifier record.
- Good, because finding the contactgegevens of a party needs no join through an identifier table.
- Good, because the identiteit UUID stays the same when the HMAC changes.
- Bad, because a party that logs in with two different identifiers becomes two identiteiten, each with its own
  contactgegevens, unless the Mijn Omgeving always picks the same identifier.
- Bad, because supporting several HMACs per identiteit later means moving the column to a table of its own.

### Confirmation

The OpenAPI document has `hmac` as a required field of the `POST /identiteiten` request, and no identifier resource.

## Pros and Cons of the Options

### 1. One `hmac` column on `identiteit`

- Good, because it is the smallest model that works.
- Bad, because one identiteit can't be reached through two identifiers.

### 2. Separate identifier record

- Good, because a KvK number and an RSIN could lead to the same identiteit.
- Bad, because it adds a table, a resource and a join for a case that the Mijn Omgeving can avoid by always hashing
  the same identifier.

### 3. HMAC as the key of the identiteit

- Good, because it needs no UUID.
- Bad, because a key rotation would change the key of every identiteit, and with it every reference in the audit log.
- Bad, because the HMAC would appear in resource paths, and so in access logs.

## More Information

- The format and uniqueness of the `hmac` are in [0006](0006-hmac-unique-by-index.md).
