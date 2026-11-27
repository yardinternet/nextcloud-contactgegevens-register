---
status: accepted
date: 2026-11-26
decision-makers: Marc Schwartz
---

# Key rotation over an overlap window; stale identiteiten are purged by date

## Context and Problem Statement

The Mijn Omgeving computes each HMAC with a secret key ([0002](0002-hmac-by-mijn-omgeving.md)), and that key has to be
replaced from time to time. A new key gives every identifier a new HMAC, so the `hmac` of every identiteit
([0004](0004-hmac-on-identiteit.md)) has to be replaced. The Mijn Omgeving sees an identifier only when its party logs
in and stores none, so it can't recompute all HMACs in one batch.

How does the register follow a key rotation, and what happens to the identiteiten whose party doesn't log in?

## Decision Drivers

- The contactgegevens of a party that keeps logging in should survive a rotation.
- The register must not keep data that nobody can reach any more.
- The register stays out of the key management of the Mijn Omgeving ([0002](0002-hmac-by-mijn-omgeving.md)).
- An HMAC only travels in a request body ([0003](0003-own-model-and-api.md)).
- The identiteit UUID must stay the same, for the audit log ([0008](0008-audit-by-identiteit-uuid.md)).

## Considered Options

1. Hard cut: the old key is retired at once and every identiteit is deleted
2. Overlap window: `POST /identiteiten` re-keys an identiteit at login; stale identiteiten are found by the date on
   which their `hmac` was set
3. Overlap window with a separate endpoint that replaces the `hmac` of an identiteit
4. Overlap window; stale identiteiten are found by a key version that the Mijn Omgeving sends
5. Overlap window without a purge

## Decision Outcome

Chosen option: **2, an overlap window in which `POST /identiteiten` re-keys at login, and a purge by date.**

During the window the Mijn Omgeving holds both keys. At each login it sends the HMAC of the new key as `hmac` and the
HMAC of the old key as `vorigeHmac`:

| Situation | Result |
|---|---|
| An identiteit with `hmac` exists | That identiteit is returned. |
| Only an identiteit with `vorigeHmac` exists | Its `hmac` is replaced; the same `uuid` is returned. |
| Neither exists | A new identiteit is created with `hmac`. |
| Both exist | The identiteit with `hmac` is returned; the other one is left for the purge. |

Outside a window the Mijn Omgeving leaves `vorigeHmac` out.

When the window ends, the Mijn Omgeving retires the old key. An identiteit that wasn't re-keyed can't be reached any
more and is lost. To find those, `identiteit` has a column `hmac_gezet_op`: the moment (a UTC datetime) at which its
`hmac` was last set, by a create or a re-key. The register fills it in itself. An `occ` command takes a date and
deletes every identiteit, with its contactgegevens, whose `hmac` was set on that date or earlier (UTC). After a
rotation it is run with the last day before the window started. Running it with the start date itself would also
delete the identiteiten that were re-keyed on the first day of the window.

When a key has to be retired at once, there is no window. Every identiteit is then lost and purged.

### Consequences

- Good, because a party that logs in during the window keeps its contactgegevens, their verification and its UUID.
- Good, because a login is still one request, and no endpoint is needed that looks up an identiteit without creating
  it.
- Good, because the register needs no key version: it only records a moment, which it knows itself.
- Good, because the purge removes contactgegevens that nobody can reach.
- Bad, because a party that doesn't log in during the window has to enter and verify its contactgegevens again.
- Bad, because the old key stays in use until the window ends.
- Bad, because `hmac_gezet_op` shows that a party logged in at or after a certain moment.

### Confirmation

Tests cover the four situations of the table, and show that a re-key keeps the `uuid` and sets `hmac_gezet_op`. A
test shows that the purge deletes exactly the identiteiten whose `hmac` was set on or before the given date, with
their contactgegevens, and leaves their audit entries.

## Pros and Cons of the Options

### 1. Hard cut

- Good, because it needs no support in the register.
- Bad, because every party has to enter and verify its contactgegevens again at every rotation.

### 2. Re-key at login through `POST /identiteiten`, purge by date

- Good, because the look-up and the re-key are one request in one transaction.
- Good, because the moment needs nothing from the Mijn Omgeving.
- Bad, because it adds an optional field to the request and a column to the table.

### 3. Separate endpoint that replaces the `hmac`

- Good, because `POST /identiteiten` keeps a single purpose.
- Bad, because the Mijn Omgeving first has to find the identiteit by its old HMAC, and that request creates an
  identiteit when none exists.
- Bad, because the look-up and the replacement are two requests, so they are not atomic.
- Bad, because replacing an `hmac` with one that already exists needs a conflict response.

### 4. Key version sent by the Mijn Omgeving

- Good, because stale identiteiten can be told apart exactly.
- Bad, because the register would store and check a part of the key management of the Mijn Omgeving.
- Bad, because the Mijn Omgeving has to send the version with every request.

### 5. No purge

- Good, because the table stays as it is.
- Bad, because email addresses that nobody can reach any more would stay in the database for good.

## More Information

- `POST /identiteiten` rejects a request in which `vorigeHmac` equals `hmac`.
- The length of the window is decided by the Mijn Omgeving.
