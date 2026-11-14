<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<HelpRequest> */
class HelpRequestMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helpdesk_requests', HelpRequest::class);
	}

	/** @throws \OCP\AppFramework\Db\DoesNotExistException */
	public function findById(int $id): HelpRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Requests, most recently active first.
	 *
	 * @param string|null $uid only this person's; null for everyone's
	 * @param string|null $status HelpRequest::OPEN or ::SOLVED; null for both
	 * @return HelpRequest[]
	 */
	public function findList(?string $uid, ?string $status, int $limit = 200): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		if ($uid !== null) {
			$qb->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		}
		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		$qb->orderBy('updated_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/** @return HelpRequest[] requests waiting to be (re)sent whose next try is due */
	public function findDue(int $now, int $limit = 20): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->in('state', $qb->createNamedParameter([HelpRequest::PENDING, HelpRequest::FAILED], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('next_try_at'),
				$qb->expr()->lte('next_try_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)),
			))
			->orderBy('id')
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/** Requests still not sent, for the admin's overview. */
	public function countUnsent(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($this->getTableName())
			->where($qb->expr()->in('state', $qb->createNamedParameter([HelpRequest::PENDING, HelpRequest::FAILED], IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}
}
