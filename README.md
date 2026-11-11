# Contactgegevens Register

Nextcloud app voor het koppelen van (encrypted) BSNs aan contactgegevens.

Deze app kan gebruikt worden in combinatie met een MijnOmgeving van een gemeente, waar een gebruiker inlogt met DigiD
en er dus een BSN bekend is, maar geen contactgegevens.

## Vereisten

- Nextcloud 34 of 35
- PHP 8.3 of hoger

## Development

Installeer de dependencies (inclusief de tools onder `vendor-bin/`):

```bash
composer install
```

Beschikbare scripts:

| Commando                 | Omschrijving                                        |
|--------------------------|-----------------------------------------------------|
| `composer lint`          | PHP syntax check                                    |
| `composer cs:check`      | Code style controleren (php-cs-fixer)               |
| `composer cs:fix`        | Code style automatisch herstellen                   |
| `composer psalm`         | Statische analyse                                   |
| `composer test:unit`     | Unit tests (draaien binnen een Nextcloud-installatie) |
| `composer openapi`       | `openapi.json` opnieuw genereren                    |
| `composer rector`        | Rector refactorings toepassen                       |

Commits volgen de [Conventional Commits](https://www.conventionalcommits.org/) conventie; dit wordt in CI
gecontroleerd voor pull requests.

## Licentie

[AGPL-3.0-or-later](LICENSE)
