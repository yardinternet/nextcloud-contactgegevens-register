<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Unit\Service;

use OCA\ContactgegevensRegister\Db\ContactgegevenMapper;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Exception\DubbelContactgegevenException;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\DB\Exception;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * The race between two requests can't be reproduced against a real database in a test, so it's simulated here.
 */
final class ContactgegevenServiceTest extends TestCase {
	public function testAddThrowsDubbelWhenAnotherRequestInsertedTheSameWaarde(): void {
		$identiteit = new Identiteit();
		$identiteit->setId(1);
		$identiteitMapper = $this->createMock(IdentiteitMapper::class);
		$identiteitMapper->method('findByUuid')->willReturn($identiteit);

		$mapper = $this->createMock(ContactgegevenMapper::class);
		$mapper->method('existsWaarde')->willReturn(false);
		$mapper->method('findByIdentiteit')->willReturn([]);
		$mapper->method('insert')->willThrowException(new class('') extends Exception {
			#[\Override]
			public function getReason(): ?int {
				return self::REASON_UNIQUE_CONSTRAINT_VIOLATION;
			}
		});

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('rollBack');

		$this->expectException(DubbelContactgegevenException::class);
		(new ContactgegevenService($mapper, $identiteitMapper, $db))
			->add('0b5b2d8e-6a43-4c1f-9a51-2a3c1f1e7d10', 'email', 'jan@example.org');
	}
}
