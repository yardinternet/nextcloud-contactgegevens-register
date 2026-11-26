<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Unit\Controller;

use OCA\ContactgegevensRegister\Controller\ContactgegevenController;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContactgegevenControllerTest extends TestCase {
	private const string IDENTITEIT_UUID = '0b5b2d8e-6a43-4c1f-9a51-2a3c1f1e7d10';
	private const array CONTACTGEGEVEN = [
		'uuid' => '5d1f6c55-3f0e-4e8a-9a0e-6a8f5b8a2c11',
		'identiteit' => self::IDENTITEIT_UUID,
		'soort' => 'email',
		'waarde' => 'jan@example.org',
		'geverifieerdOp' => '2026-11-26',
		'isPrimair' => true,
	];

	private ContactgegevenService&MockObject $service;
	private ContactgegevenController $controller;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(ContactgegevenService::class);
		$this->controller = new ContactgegevenController($this->createMock(IRequest::class), $this->service);
	}

	public function testIndexReturnsTheContactgegevens(): void {
		$this->service->expects($this->once())
			->method('findByIdentiteit')
			->with(self::IDENTITEIT_UUID, true)
			->willReturn([self::CONTACTGEGEVEN]);

		$response = $this->controller->index(self::IDENTITEIT_UUID, true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([self::CONTACTGEGEVEN], $response->getData());
	}

	public function testIndexReturns404ForUnknownIdentiteit(): void {
		$this->service->method('findByIdentiteit')->willThrowException(new NietGevondenException('Identiteit not found'));

		$response = $this->controller->index(self::IDENTITEIT_UUID);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Identiteit not found'], $response->getData());
	}
}
