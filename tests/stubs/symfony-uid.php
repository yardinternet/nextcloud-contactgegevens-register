<?php

declare(strict_types=1);

// Psalm stub: the server ships symfony/uid in 3rdparty, nextcloud/ocp doesn't.

namespace Symfony\Component\Uid;

class Uuid {
	public static function v4(): UuidV4 {
	}

	public function toRfc4122(): string {
	}
}

class UuidV4 extends Uuid {
}
