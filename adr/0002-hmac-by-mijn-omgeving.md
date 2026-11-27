---
status: accepted
date: 2026-11-23
decision-makers: Marc Schwartz
---

# Mijn Omgeving computes the HMAC; contact details are stored in plain text

## Context and Problem Statement

A party logs in to the Mijn Omgeving with DigiD or eHerkenning, which yields an identifier such as a BSN or a KvK
number. The Mijn Omgeving loads the party's agenda by email address, so it needs a place that links the identifier to
email addresses. That place is this register, and the Mijn Omgeving is its only client.

No identifier may appear in plain text in the register's database, logs or backups. Something has to turn the
identifier into a key that can be looked up but not reversed, and someone has to hold the secret for that. Where does
that happen, and in what form are the contact details stored?

## Decision Drivers

- A copy of the database, a backup or a Nextcloud administrator must not reveal identifiers.
- A BSN has only 9 digits, so an unkeyed hash of it can be reversed by brute force within minutes.
- The register must be able to validate and return email addresses ([0003](0003-own-model-and-api.md)).
- The data must convert to OpenRegister without help from another system
  ([0001](0001-dedicated-nextcloud-app.md)).
- The register must stay simple enough to hand over.

## Considered Options

1. The Mijn Omgeving computes an HMAC of the identifier and holds the secret; the register stores the HMAC and
   plain-text contact details
2. As option 1, but the Mijn Omgeving also encrypts the contact details before sending them
3. The register computes the HMAC, with the secret in the Nextcloud configuration
4. Unkeyed SHA-256 of the identifier, computed anywhere

## Decision Outcome

Chosen option: **1, the Mijn Omgeving computes the HMAC, and the register stores contact details in plain text.**

- The Mijn Omgeving turns the identifier into an HMAC with a secret that never reaches Nextcloud. How it does that
  (algorithm input, normalisation, key rotation) is its own concern.
- The register receives only the HMAC and links everything to it. It never sees the identifier and doesn't know what
  kind of identifier it was ([0005](0005-identifier-type-agnostic.md)).
- Email addresses, and later phone numbers, are stored as received, in plain text.

### Consequences

- Good, because the register, its database and its backups hold no identifier that can be reversed without a secret
  stored elsewhere.
- Good, because the register contains no cryptographic code to get wrong.
- Good, because the register can validate that a `waarde` is an email address.
- Good, because a conversion to OpenRegister is a plain copy.
- Bad, because a copy of the database reveals email addresses. They are linked to an HMAC, not to a person, but an
  email address is personal data on its own and often names its owner.
- Bad, because a Nextcloud administrator can read the email addresses.
- Neutral, because the HMAC format is a contract between the Mijn Omgeving and the register
  ([0006](0006-hmac-unique-by-index.md)).

### Confirmation

The acceptance test inspects the database, logs and a backup for plain identifiers. Tests assert that the API rejects
values that are not a well-formed HMAC ([0006](0006-hmac-unique-by-index.md)), which catches a client that sends a
plain identifier by mistake.

## Pros and Cons of the Options

### 1. Mijn Omgeving computes the HMAC, contact details in plain text

- Good, because the secret and the data are stored in different systems, so one breach doesn't link contact details
  to identifiers.
- Good, because the Mijn Omgeving already sees the plain identifier at login, so no new system learns it.
- Bad, because contact details are readable by anyone with access to the database.

### 2. Mijn Omgeving also encrypts the contact details

- Good, because a copy of the database would reveal nothing at all.
- Bad, because the register couldn't validate or deduplicate addresses without an extra hash per address.
- Bad, because a conversion to OpenRegister would have to run through the Mijn Omgeving to decrypt every address.
- Bad, because the Mijn Omgeving would have to build and maintain encryption and key rotation for it.

### 3. Register computes the HMAC, secret in Nextcloud

- Good, because the Mijn Omgeving stays simpler.
- Bad, because the register would receive plain BSNs, which must never happen.
- Bad, because the secret and the data would live on the same server, readable by its administrators.

### 4. Unkeyed SHA-256

- Good, because no key management is needed.
- Bad, because a 9-digit identifier can be brute-forced quickly, so this is not real pseudonymisation.

## More Information

- Version 1 stores email addresses only. Phone numbers and the preferred channel come later
  ([0003](0003-own-model-and-api.md)).
- Hosting-level protection (mTLS, IP whitelist, secret hostname) is described in the hosting guide, not in this app.
