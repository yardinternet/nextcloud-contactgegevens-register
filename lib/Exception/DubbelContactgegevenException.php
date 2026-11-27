<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Exception;

use Exception;

/**
 * The identiteit already has this waarde for this soort. The message is safe to return to the client.
 */
final class DubbelContactgegevenException extends Exception {
}
