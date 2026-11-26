<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister;

/**
 * Response shapes for the OpenAPI document.
 *
 * @psalm-type ContactgegevensRegisterIdentiteit = array{
 *     uuid: string,
 * }
 *
 * @psalm-type ContactgegevensRegisterContactgegeven = array{
 *     uuid: string,
 *     identiteit: string,
 *     soort: string,
 *     waarde: string,
 *     geverifieerdOp: ?string,
 *     isPrimair: bool,
 * }
 *
 * @psalm-type ContactgegevensRegisterFout = array{
 *     message: string,
 * }
 *
 * @psalm-suppress UnusedClass Only read by the OpenAPI extractor
 */
final class ResponseDefinitions {
}
