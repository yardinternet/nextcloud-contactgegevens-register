---
status: accepted
date: 2026-11-10
decision-makers: Marc Schwartz
---

# Hashing and encryption in the backend, keys outside Nextcloud

## Context and Problem Statement

The register links identifiers (BSN, RSIN, KvK number, vestigingsnummer) to email addresses and phone numbers. No
identifier may appear in plain text in the database, logs or backups, and contact details
must be stored encrypted. Something has to compute the pseudonyms and encrypt the addresses, and someone has to hold the
keys. Where does that happen?

## Decision Drivers

- A copy of the database, a backup or a Nextcloud administrator must not reveal identifiers or contact details.
- A BSN has only 9 digits, so an unkeyed hash of it can be reversed by brute force within minutes.
- Key rotation must be possible without converting all data at once.
- The register must stay simple enough to hand over.

## Considered Options

1. The Mijn Omgeving backend hashes and encrypts; keys live in a secret store (e.g. Vault) or as a backend secret
2. The register app hashes and encrypts, with keys in the Nextcloud configuration
3. OpenRegister-style object encryption with the Nextcloud instance secret
4. Unkeyed SHA-256 of the identifier, computed anywhere

## Decision Outcome

Chosen option: **1, the backend hashes and encrypts, and the keys never reach Nextcloud.** The register stores what it
receives:

- `pseudoniem` = `HMAC-SHA256(key, type + separator + normalised identifier)`, as 64 lowercase hex characters
  (see [0006](0006-pseudonym-unique-on-its-own.md));
- `adresHash` = `HMAC-SHA256(key, soortDigitaalAdres + separator + normalised value)`, for duplicate checks;
- `adres` = `enc:<key version>:<base64 ciphertext>`;
- `sleutelversie`, the HMAC key version, so keys can be rotated record by record.

The backend also sends the verification email; the register only stores the hash of the token.

### Consequences

- Good, because the register, its database and its backups hold nothing that can be reversed without keys stored
  elsewhere.
- Good, because a Nextcloud administrator cannot decrypt anything.
- Good, because the register contains no cryptographic code to get wrong.
- Bad, because the register cannot search on, validate or display addresses. Requests that try to search on encrypted
  values get a clear error.
- Bad, because the register can't check that an `adres` is a real email address or phone number. As a safeguard it
  rejects `adres` values that don't start with `enc:` and hashes that aren't 64 lowercase hex characters, which
  catches a backend that sends plaintext by mistake.
- Bad, because a migration to a real Klantinteracties API must decrypt `adres`, so it has to run through the backend.
- Neutral, because the pseudonym format becomes a contract between backend and register that both sides must keep.

### Confirmation

Tests assert that the API rejects plaintext-looking `adres` values and malformed hashes. The acceptance test inspects
the database, logs and a backup for plain identifiers, email addresses and phone numbers.

## Pros and Cons of the Options

### 1. Backend hashes and encrypts, keys outside Nextcloud

- Good, because keys and data are stored in different systems, so one breach doesn't expose both.
- Good, because the backend already sees the plain values at login, so no new system learns them.
- Bad, because the backend team has to implement and maintain the hashing, encryption and key rotation.

### 2. Register app hashes and encrypts, keys in Nextcloud

- Good, because the backend stays simpler.
- Bad, because the register would receive plain BSNs, which must never happen.
- Bad, because keys and data would live on the same server, readable by its administrators.

### 3. OpenRegister object encryption

- Good, because it exists already.
- Bad, because it uses the instance secret, so any administrator can decrypt everything
  (see [0001](0001-dedicated-nextcloud-app.md)).

### 4. Unkeyed SHA-256

- Good, because no key management is needed.
- Bad, because a 9-digit identifier can be brute-forced quickly, so this is not real pseudonymisation.

## More Information

- Still open with the backend team: confirm that the backend encrypts email addresses and phone numbers (not only the
  identifiers), where the keys are stored, and the exact HMAC input format (separator and normalisation).
- Normalisation: lowercase for email addresses, E.164 for phone numbers.
- Hosting-level protection (mTLS, IP whitelist, secret hostname) is described in the hosting guide, not in this app.
