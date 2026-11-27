<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Exception;

use Exception;

/**
 * The request holds invalid input. The message is safe to return to the client.
 */
final class OngeldigeInvoerException extends Exception {
}
