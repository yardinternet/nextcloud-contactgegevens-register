<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Db;

use DateTimeImmutable;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Identiteit>
 * @psalm-suppress UnusedClass Used by the API and the purge, which are not built yet.
 */
class IdentiteitMapper extends QBMapper {
	public const string TABLE = 'cgr_identiteit';

	/** Stays below Oracle's limit of 1000 items in an IN list. */
	private const int CHUNK_SIZE = 1000;

	/** @psalm-suppress PossiblyUnusedMethod Instantiated by the DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, Identiteit::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the API, which is not built yet.
	 */
	public function findById(int $id): Identiteit {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the API, which is not built yet.
	 */
	public function findByUuid(string $uuid): Identiteit {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the API, which is not built yet.
	 */
	public function findByHmac(string $hmac): Identiteit {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('hmac', $qb->createNamedParameter($hmac)));

		return $this->findEntity($qb);
	}

	/**
	 * Deletes the identiteiten with these ids. The foreign key removes their contactgegevens.
	 *
	 * @param list<int> $ids
	 * @return int the number of deleted identiteiten
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the purge, which is not built yet.
	 */
	public function deleteByIds(array $ids): int {
		$deleted = 0;
		foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete($this->getTableName())
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleted += $qb->executeStatement();
		}

		return $deleted;
	}

	/**
	 * The ids of the identiteiten whose hmac was set before this moment.
	 *
	 * @return list<int>
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the purge, which is not built yet.
	 */
	public function findIdsWithHmacGezetOpBefore(DateTimeImmutable $moment): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->lt('hmac_gezet_op', $qb->createNamedParameter($moment, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
			->orderBy('id');

		return $this->fetchIds($qb);
	}

	/**
	 * The ids of the identiteiten without contactgegevens whose hmac was set before this moment.
	 *
	 * @return list<int>
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod Used by the purge, which is not built yet.
	 */
	public function findIdsWithoutContactgegevensBefore(DateTimeImmutable $moment): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id')
			->from($this->getTableName(), 'i')
			->leftJoin('i', ContactgegevenMapper::TABLE, 'c', $qb->expr()->eq('c.identiteit_id', 'i.id'))
			->where($qb->expr()->lt('i.hmac_gezet_op', $qb->createNamedParameter($moment, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
			->andWhere($qb->expr()->isNull('c.id'))
			->orderBy('i.id');

		return $this->fetchIds($qb);
	}

	/**
	 * @return list<int>
	 * @throws Exception
	 * @psalm-suppress UnusedMethod Used by the purge, which is not built yet.
	 */
	private function fetchIds(IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		$ids = array_map(intval(...), $result->fetchFirstColumn());
		$result->closeCursor();

		return $ids;
	}
}
