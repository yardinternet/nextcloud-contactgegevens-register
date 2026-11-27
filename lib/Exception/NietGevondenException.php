<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Exception;

use Exception;

/**
 * No identiteit or contactgegeven has the requested UUID. The message is safe to return to the client.
 */
final class NietGevondenException extends Exception {
}
