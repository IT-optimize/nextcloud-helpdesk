<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Helpdesk 1.0: requests get a status (open or solved) and a history: messages
 * to the provider (emailed in the same thread), notes that stay in the company,
 * and who marked the request solved or opened it again.
 */
class Version010000Date20261113090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$requests = $schema->getTable('helpdesk_requests');
		if (!$requests->hasColumn('status')) {
			// open | solved, set by the people in the company (delivery is in "state")
			$requests->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'open']);
			$requests->addColumn('solved_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			// the Message-ID of the request email, so messages after it join the same thread
			$requests->addColumn('message_id', Types::STRING, ['notnull' => false, 'length' => 255]);
			// last time anything happened, for sorting the list
			$requests->addColumn('updated_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$requests->addIndex(['status', 'updated_at'], 'hdr_requests_status');
		}

		if (!$schema->hasTable('helpdesk_entries')) {
			$table = $schema->createTable('helpdesk_entries');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('request_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('author_name', Types::STRING, ['notnull' => false, 'length' => 255]);
			// message (emailed to the provider) | note (stays here) | solved | reopened
			$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('body', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			// delivery, for messages only: as on helpdesk_requests
			$table->addColumn('message_id', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('state', Types::STRING, ['notnull' => false, 'length' => 16]);
			$table->addColumn('attempts', Types::INTEGER, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$table->addColumn('last_error', Types::STRING, ['notnull' => false, 'length' => 500]);
			$table->addColumn('next_try_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->addColumn('admins_told', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('sent_at', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['request_id', 'created_at'], 'hde_entries_request');
			$table->addIndex(['state', 'next_try_at'], 'hde_entries_due');
		}
		return $schema;
	}

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** Requests written before this step: last activity is when they were written. */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('helpdesk_requests')
			->set('updated_at', 'created_at')
			->where($qb->expr()->isNull('updated_at'))
			->executeStatement();
	}
}
