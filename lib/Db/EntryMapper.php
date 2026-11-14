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

/** @template-extends QBMapper<Entry> */
class EntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'helpdesk_entries', Entry::class);
	}

	/** @return Entry[] oldest first */
	public function findForRequest(int $requestId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('request_id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	/** @return Entry[] messages waiting to be (re)sent whose next try is due */
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

	public function countUnsent(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($this->getTableName())
			->where($qb->expr()->in('state', $qb->createNamedParameter([HelpRequest::PENDING, HelpRequest::FAILED], IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** Message-IDs of the messages already sent in a request's thread, oldest first. */
	public function sentMessageIds(int $requestId): array {
		$ids = [];
		foreach ($this->findForRequest($requestId) as $entry) {
			if ($entry->getKind() === Entry::MESSAGE && $entry->getState() === HelpRequest::SENT && $entry->getMessageId()) {
				$ids[] = $entry->getMessageId();
			}
		}
		return $ids;
	}
}
