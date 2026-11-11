---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# Pseudonym unique on its own; the type is part of the HMAC input

## Context and Problem Statement

The register looks up a partij by the pseudonym of its identifier. A BSN and an RSIN are both 9 digits, so the same
digits can identify two different parties. A pseudonym could be unique per type, or on its own. Which
uniqueness constraint does the database enforce?

## Decision Drivers

- Uniqueness must be enforced by the database, so it holds under concurrent inserts
  ([0001](0001-dedicated-nextcloud-app.md)).
- A lookup should need only the pseudonym.
- Collisions between identifier types must be impossible, not just unlikely.

## Considered Options

1. Unique on `(type, pseudoniem)`, with the type not part of the HMAC input
2. Unique on `pseudoniem` alone, with the type part of the HMAC input
3. Unique on `(type, pseudoniem)` and the type also part of the HMAC input

## Decision Outcome

Chosen option: **2, `pseudoniem` is unique on its own**, enforced by a unique index. The backend computes
`HMAC-SHA256(key, type + separator + normalised identifier)`, where `type` is the `codeSoortObjectId` (`bsn`, `rsin`,
`kvk_nummer`, `vestigingsnummer`) or `eherkenning_pseudo` ([0005](0005-identifier-types.md)).

Including the type in the HMAC input is a hard rule in the contract with the backend. Because the same digits with a
different type give a different pseudonym, cross-type collisions are ruled out. HMAC-SHA256 output is 256 bits, so
accidental collisions are not a practical concern.

The pseudonym is stored as 64 lowercase hex characters. Lowercase matters because MySQL's default collation compares
case-insensitively.

### Consequences

- Good, because a lookup needs only the pseudonym, and the index is simple.
- Good, because the database rejects duplicates even when two requests insert at the same moment. A duplicate returns
  409 with the UUID of the existing record.
- Bad, because correctness depends on the backend following the contract. If the type were left out of the input, a
  BSN and an RSIN with the same digits would collide.
- Bad, because the type codes and the separator are frozen. Changing either changes every pseudonym.
- Neutral, because key rotation recomputes every pseudonym and increments `sleutelversie`.

### Confirmation

Tests prove that the unique index rejects a duplicate pseudonym under concurrent inserts, on MySQL, PostgreSQL and
SQLite. The API rejects pseudonyms that are not 64 lowercase hex characters.

## Pros and Cons of the Options

### 1. Unique on `(type, pseudoniem)`, type not in the input

- Good, because the backend contract is simpler.
- Bad, because every lookup also needs the type.
- Bad, because the same digits give the same pseudonym for different types, which leaks that a BSN and an RSIN share
  digits.

### 2. Unique on `pseudoniem`, type in the input

- Good, because lookups and the index are simple, and collisions across types are impossible.
- Bad, because it relies on a rule the register can't verify itself.

### 3. Both

- Good, because it adds a database safeguard if the backend breaks the contract.
- Bad, because the extra column in the index protects against nothing that option 2 doesn't already rule out, and
  every lookup again needs the type.

## More Information

- Still open with the backend team: the exact HMAC input format (separator, normalisation per identifier type) and
  written confirmation that the type is part of it.
- The same pattern applies to `adresHash`, which includes `soortDigitaalAdres` in its input
  ([0002](0002-crypto-in-backend.md)).
