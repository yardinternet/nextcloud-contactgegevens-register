<?php

/**
 * SPDX-FileCopyrightText: 2026 Marc Schwartz
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Unit\Controller;

use OCA\ContactgegevensRegister\AppInfo\Application;
use OCA\ContactgegevensRegister\Controller\ApiController;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$controller = new ApiController(Application::APP_ID, $request);

		$this->assertEquals('Hello world!', $controller->index()->getData()['message']);
	}
}
