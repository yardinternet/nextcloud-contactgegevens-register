<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Unit\Controller;

use OCA\ContactgegevensRegister\Controller\IdentiteitController;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\Service\IdentiteitService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IdentiteitControllerTest extends TestCase {
	private const string HMAC = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const string VORIGE_HMAC = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
	private const string UUID = '0b5b2d8e-6a43-4c1f-9a51-2a3c1f1e7d10';

	private IdentiteitService&MockObject $service;
	private IdentiteitController $controller;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(IdentiteitService::class);
		$this->controller = new IdentiteitController($this->createMock(IRequest::class), $this->service);
	}

	public function testCreateReturns201WhenCreated(): void {
		$this->service->expects($this->once())
			->method('findOrCreate')
			->with(self::HMAC, null)
			->willReturn(['identiteit' => $this->identiteit(), 'aangemaakt' => true]);

		$response = $this->controller->create(self::HMAC);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame(['uuid' => self::UUID], $response->getData());
	}

	public function testCreateReturns200WhenFoundOrReKeyed(): void {
		$this->service->expects($this->once())
			->method('findOrCreate')
			->with(self::HMAC, self::VORIGE_HMAC)
			->willReturn(['identiteit' => $this->identiteit(), 'aangemaakt' => false]);

		$response = $this->controller->create(self::HMAC, self::VORIGE_HMAC);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['uuid' => self::UUID], $response->getData());
	}

	public function testCreateReturns400ForInvalidInput(): void {
		$this->service->method('findOrCreate')
			->willThrowException(new OngeldigeInvoerException('hmac must be 64 lowercase hexadecimal characters'));

		$response = $this->controller->create('123456789');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'hmac must be 64 lowercase hexadecimal characters'], $response->getData());
	}

	private function identiteit(): Identiteit {
		$identiteit = new Identiteit();
		$identiteit->setUuid(self::UUID);
		return $identiteit;
	}
}
