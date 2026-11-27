<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\ContactgegevensRegister\Db\Identiteit;
use OCA\ContactgegevensRegister\Db\IdentiteitMapper;
use OCA\ContactgegevensRegister\Service\IdentiteitService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * The race between two requests can't be reproduced against a real database in a test, so it's simulated here.
 */
final class IdentiteitServiceTest extends TestCase {
	private const string HMAC = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	public function testReturnsTheWinnerWhenAnotherRequestInsertedTheSameHmac(): void {
		$winner = new Identiteit();
		$winner->setUuid('0b5b2d8e-6a43-4c1f-9a51-2a3c1f1e7d10');

		$mapper = $this->createMock(IdentiteitMapper::class);
		$calls = 0;
		$mapper->expects($this->exactly(2))
			->method('findByHmac')
			->with(self::HMAC)
			->willReturnCallback(static function () use (&$calls, $winner): Identiteit {
				// Not there inside the transaction, there after the other request committed.
				if ($calls++ === 0) {
					throw new DoesNotExistException('');
				}
				return $winner;
			});
		$mapper->expects($this->once())
			->method('insert')
			->willThrowException(new class('') extends Exception {
				#[\Override]
				public function getReason(): ?int {
					return self::REASON_UNIQUE_CONSTRAINT_VIOLATION;
				}
			});

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('rollBack');
		$db->expects($this->never())->method('commit');

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable());

		$result = (new IdentiteitService($mapper, $db, $timeFactory))->findOrCreate(self::HMAC);

		$this->assertSame(['identiteit' => $winner, 'aangemaakt' => false], $result);
	}

	public function testRethrowsOtherDatabaseErrors(): void {
		$mapper = $this->createMock(IdentiteitMapper::class);
		$mapper->method('findByHmac')->willThrowException(new DoesNotExistException(''));
		$mapper->method('insert')->willThrowException(new Exception('connection lost'));

		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('now')->willReturn(new DateTimeImmutable());

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('connection lost');
		(new IdentiteitService($mapper, $this->createMock(IDBConnection::class), $timeFactory))->findOrCreate(self::HMAC);
	}
}
