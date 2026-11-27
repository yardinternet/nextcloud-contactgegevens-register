---
status: accepted
date: 2026-11-26
decision-makers: Marc Schwartz
---

# An identiteit may exist without contactgegevens

## Context and Problem Statement

The Mijn Omgeving needs the UUID of an identiteit for every other request, and gets it by sending the HMAC at login
([0003](0003-own-model-and-api.md)). At that moment the party may not have entered an email address yet. Later, the
party can have its last contactgegeven forgotten ([0007](0007-verification-derived-from-date.md)). May an identiteit
exist without contactgegevens?

## Decision Drivers

- Getting the UUID for an HMAC should be one request that can be repeated safely.
- An HMAC only travels in a request body ([0003](0003-own-model-and-api.md)).
- Forgetting a contactgegeven must always succeed, at once.
- The register should store as little as possible.

## Considered Options

1. An identiteit may exist without contactgegevens
2. An identiteit always has at least one contactgegeven: it is created together with its first one, and deleted
   together with its last one
3. An identiteit always has at least one contactgegeven: it is created together with its first one, and deleting the
   last one is refused

## Decision Outcome

Chosen option: **1, an identiteit may exist without contactgegevens.** `POST /identiteiten` creates an identiteit from
the `hmac` alone and returns its `uuid`. Deleting the last contactgegeven leaves the identiteit in place. An identiteit
is deleted by `DELETE /identiteiten/{uuid}`, by the purge after a key rotation
([0009](0009-key-rotation-overlap-window.md)), or by the purge of empty identiteiten.

An identiteit without contactgegevens is not kept for long. Once its `hmac_gezet_op` is more than 24 hours ago, it is
purged: an hourly background job does this, and an `occ` command does the same on demand. The age is measured from
`hmac_gezet_op` ([0009](0009-key-rotation-overlap-window.md)), so a party that logs in for the first time has a day to
add an address. The 24 hours are fixed for now and can become a setting later.

### Consequences

- Good, because finding or creating an identiteit stays one request, with the HMAC in its body.
- Good, because the UUID of a party stays the same while its contactgegevens come and go, so its audit entries stay
  under one UUID ([0008](0008-audit-by-identiteit-uuid.md)). The exception is an identiteit that stays empty for more
  than 24 hours: it is purged, and the next login gets a new UUID. When the Mijn Omgeving gets a `404` for a UUID, it
  sends the HMAC to `POST /identiteiten` again.
- Good, because deleting a contactgegeven has no side effect on the identiteit.
- Good, because an HMAC without contact details is kept for 24 hours at most.
- Bad, because every party that logs in gets an identiteit, also when it never enters an address. Such a row holds
  only an HMAC until it is purged.
- Bad, because the purge of empty identiteiten is a background job that has to be kept running.
- Bad, because the list of contactgegevens of an identiteit can be empty, which the Mijn Omgeving has to handle.

### Confirmation

Tests show that `POST /identiteiten` with only an `hmac` returns a UUID, and that the identiteit still exists after its
last contactgegeven is deleted. A test shows that the purge of empty identiteiten deletes exactly those without
contactgegevens whose `hmac_gezet_op` is more than 24 hours ago.

## Pros and Cons of the Options

### 1. An identiteit may exist without contactgegevens

- Good, because it is the simplest API and needs no rule that spans two tables.
- Bad, because the register stores an HMAC for parties without contact details.

### 2. At least one; deleted together with its last contactgegeven

- Good, because the register holds no HMAC without contact details.
- Bad, because the Mijn Omgeving needs a look-up that doesn't create. With the HMAC in a request body that is a
  `POST` that isn't a create; as a `GET` it would put the HMAC in the query string, and so in access logs.
- Bad, because deleting a contactgegeven can make the UUID of the identiteit invalid, which the Mijn Omgeving has to
  detect.
- Bad, because a database can't enforce "at least one child row", so the rule would live in application code.

### 3. At least one; deleting the last one is refused

- Good, because a UUID never disappears as a side effect.
- Bad, because forgetting the last address would take a second, different request, while a forgotten address must
  disappear at once.
- Bad, because it needs the same look-up that doesn't create as option 2.

## More Information

- The purge of empty identiteiten is separate from the purge after a key rotation
  ([0009](0009-key-rotation-overlap-window.md)), which takes a date and also deletes identiteiten that have
  contactgegevens.
