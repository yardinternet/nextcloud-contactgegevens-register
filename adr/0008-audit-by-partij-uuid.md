---
status: accepted
date: 2026-11-11
decision-makers: Marc Schwartz
---

# Audit by partij UUID instead of pseudonym

## Context and Problem Statement

The register keeps an append-only audit log of actions (address added, verified, withdrawn, preference changed, …).
An obvious choice is to record the pseudonym in each entry. The audit log must never contain email addresses, phone
numbers or identifiers. Which reference to the partij does an entry hold?

## Decision Drivers

- Entries must stay traceable to the same partij over time, including after a key rotation.
- The log must hold no personal data beyond what is needed.
- Audit entries must survive deletion of the data they describe ([0007](0007-verification-derived-from-date.md)).

## Considered Options

1. The pseudonym
2. The partij UUID, without a foreign key
3. The internal integer `id` of the partij, with a foreign key

## Decision Outcome

Chosen option: **2, the partij UUID, deliberately without a foreign key.** The audit table holds `tijdstip`, `actie`,
`partij` (the UUID, nullable) and `bron` (the calling client, taken from its token).

### Consequences

- Good, because the UUID never changes, while a pseudonym changes whenever the HMAC key rotates.
- Good, because a partij can have several identifiers, and therefore several pseudonyms, but only one UUID.
- Good, because without a foreign key the entries survive deletion of the partij.
- Bad, because entries outlive the partij, so they need a retention rule. The retention period is still to be decided
  with the privacy officer.
- Neutral, because the UUID is still a pseudonymised reference, so the log remains personal data under the AVG and is
  treated as such.

### Confirmation

Tests check that audit entries contain no `adres`, `adresHash` or `pseudoniem` values, and that they remain after the
partij is deleted.

## Pros and Cons of the Options

### 1. Pseudonym

- Good, because it is the identifier the caller uses for lookups.
- Bad, because key rotation breaks the link between old and new entries.
- Bad, because a partij with several identifiers would appear under several pseudonyms.

### 2. Partij UUID without a foreign key

- Good, because it is stable and survives deletion.
- Bad, because nothing in the database guarantees that the UUID referred to an existing partij when it was written.
  The application writes entries in the same transaction as the change they describe.

### 3. Internal `id` with a foreign key

- Good, because the database checks the reference.
- Bad, because the foreign key would block deleting a partij, or cascade and delete its audit trail.
- Bad, because the internal `id` is never exposed outside the app, which makes entries harder to correlate in an
  investigation.

## More Information

- Still open: the audit retention period (Marc with the privacy officer).
