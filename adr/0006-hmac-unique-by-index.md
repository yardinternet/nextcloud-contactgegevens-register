---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# The HMAC is unique, enforced by a database index

## Context and Problem Statement

The register looks up an identiteit by its `hmac` ([0004](0004-hmac-on-identiteit.md)). Two requests for a party that
isn't registered yet can arrive at the same moment. The register has no identifier type to combine the HMAC with
([0005](0005-identifier-type-agnostic.md)). Which uniqueness constraint does the register enforce, and how?

## Decision Drivers

- Uniqueness must hold under concurrent inserts ([0001](0001-dedicated-nextcloud-app.md)).
- A lookup should need only the HMAC.
- One HMAC must never lead to two parties.

## Considered Options

1. A unique database index on `hmac`
2. A uniqueness check in PHP before inserting
3. No constraint; the Mijn Omgeving prevents duplicates

## Decision Outcome

Chosen option: **1, a unique database index on `hmac`.**

The register accepts an `hmac` only in one fixed format: 64 lowercase hex characters, the output of HMAC-SHA256.
Lowercase matters because MySQL's default collation compares case-insensitively.

That two different identifiers never give the same HMAC is the responsibility of the Mijn Omgeving. A BSN and an RSIN
are both 9 digits, so the same digits can identify two different parties. The Mijn Omgeving should therefore include
the kind of identifier in the HMAC input. The register can't check this.

### Consequences

- Good, because a lookup needs only the HMAC, and the index is simple.
- Good, because the database rejects duplicates even when two requests insert at the same moment. `POST /identiteiten`
  then returns the identiteit that won. A re-key can't produce a duplicate either: when the new `hmac` already exists,
  that identiteit is returned and the old one is left unchanged ([0009](0009-key-rotation-overlap-window.md)).
- Good, because the format check rejects a plain identifier that is sent by mistake.
- Bad, because correctness across identifier kinds depends on the Mijn Omgeving. If it hashed a BSN and an RSIN with
  the same digits in the same way, both would end up as one identiteit.

### Confirmation

Tests prove that the unique index rejects a duplicate `hmac` under concurrent inserts, on MySQL, PostgreSQL and
SQLite. The API rejects an `hmac` that is not 64 lowercase hex characters.

## Pros and Cons of the Options

### 1. Unique index on `hmac`

- Good, because the guarantee is given by the database and holds under concurrency.
- Bad, because the register depends on the Mijn Omgeving to keep identifier kinds apart.

### 2. Uniqueness check in PHP

- Good, because it needs no index.
- Bad, because two concurrent requests can both pass the check and both insert.

### 3. No constraint

- Good, because the register is simpler.
- Bad, because a duplicate would split a party's contact details over two records, and a lookup would return either.

## More Information

- Still open with the Mijn Omgeving team: confirmation of the HMAC format (HMAC-SHA256 as lowercase hex), and that the
  kind of identifier is part of the HMAC input.
