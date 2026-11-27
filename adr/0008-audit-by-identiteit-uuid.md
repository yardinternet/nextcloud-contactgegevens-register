---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# Audit by identiteit UUID instead of HMAC

## Context and Problem Statement

The register keeps an append-only audit log of actions (address added, verified, made primary, forgotten, …).
An obvious choice is to record the HMAC that identifies the identiteit in each entry. The audit log must never contain
email addresses, phone numbers or identifiers. Which reference to the identiteit does an entry hold?

## Decision Drivers

- Entries must stay traceable to the same identiteit over time, including after a key rotation.
- The log must hold no personal data beyond what is needed.
- Audit entries must survive deletion of the data they describe ([0007](0007-verification-derived-from-date.md)).

## Considered Options

1. The HMAC
2. The identiteit UUID, without a foreign key
3. The internal integer `id` of the identiteit, with a foreign key
4. The internal integer `id` of the identiteit, without a foreign key

## Decision Outcome

Chosen option: **2, the identiteit UUID, deliberately without a foreign key.** The audit table holds `tijdstip`,
`actie`, `identiteit_uuid` (nullable) and `bron` (the calling client, taken from its token). The column is named
`identiteit_uuid` and not `identiteit_id`, because the `_id` suffix is reserved for foreign keys that hold the internal
`id` ([0003](0003-own-model-and-api.md)).

### Consequences

- Good, because the UUID never changes, while the HMAC changes whenever the Mijn Omgeving rotates its key.
- Good, because without a foreign key the entries survive deletion of the identiteit.
- Bad, because entries outlive the identiteit, so they need a retention rule. The retention period is still to be
  decided with the privacy officer.
- Neutral, because the UUID is still a pseudonymised reference, so the log remains personal data under the AVG and is
  treated as such.

### Confirmation

Tests check that audit entries contain no `waarde` or `hmac` values, and that they remain after the identiteit is
deleted.

## Pros and Cons of the Options

### 1. HMAC

- Good, because it is the identifier the caller uses for lookups.
- Bad, because key rotation breaks the link between old and new entries.
- Bad, because it puts a value derived from an identifier in the log, where the UUID says nothing about the party.

### 2. Identiteit UUID without a foreign key

- Good, because it is stable and survives deletion.
- Bad, because nothing in the database guarantees that the UUID referred to an existing identiteit when it was
  written. The application writes entries in the same transaction as the change they describe.

### 3. Internal `id` with a foreign key

- Good, because the database checks the reference.
- Bad, because the foreign key would block deleting an identiteit, or cascade and delete its audit trail.
- Bad, because the internal `id` is never exposed outside the app, which makes entries harder to correlate in an
  investigation.

### 4. Internal `id` without a foreign key

- Good, because the entries survive deletion of the identiteit.
- Bad, because the internal `id` is never exposed outside the app. Once the identiteit is deleted, nothing maps the
  `id` back to the UUID that the Mijn Omgeving knows, so the entries can't be correlated any more.
- Bad, because some databases hand out the `id` of a deleted row again, so old entries could point at a new
  identiteit.

## More Information

- Still open: the audit retention period (Marc with the privacy officer).
