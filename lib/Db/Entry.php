<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * One line in a request's history: a message to the provider (emailed in the
 * request's thread, with the same delivery and retries as the request), a
 * note that stays in the company, or a change of status.
 *
 * @method int getRequestId()
 * @method void setRequestId(int $id)
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method ?string getAuthorName()
 * @method void setAuthorName(?string $name)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method ?string getBody()
 * @method void setBody(?string $body)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $time)
 * @method ?string getMessageId()
 * @method void setMessageId(?string $id)
 * @method ?string getState()
 * @method void setState(?string $state)
 * @method int getAttempts()
 * @method void setAttempts(int $attempts)
 * @method ?string getLastError()
 * @method void setLastError(?string $error)
 * @method ?int getNextTryAt()
 * @method void setNextTryAt(?int $time)
 * @method ?bool getAdminsTold()
 * @method void setAdminsTold(?bool $told)
 * @method ?int getSentAt()
 * @method void setSentAt(?int $time)
 */
class Entry extends Entity implements JsonSerializable {
	public const MESSAGE = 'message';
	public const NOTE = 'note';
	public const SOLVED = 'solved';
	public const REOPENED = 'reopened';

	protected $requestId;
	protected $uid;
	protected $authorName;
	protected $kind;
	protected $body;
	protected $createdAt;
	protected $messageId;
	protected $state;
	protected $attempts;
	protected $lastError;
	protected $nextTryAt;
	protected $adminsTold;
	protected $sentAt;

	public function __construct() {
		$this->addType('requestId', Types::BIGINT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('attempts', Types::INTEGER);
		$this->addType('nextTryAt', Types::BIGINT);
		$this->addType('adminsTold', Types::BOOLEAN);
		$this->addType('sentAt', Types::BIGINT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'kind' => $this->kind,
			'uid' => $this->uid,
			'name' => $this->authorName ?: $this->uid,
			'body' => $this->body,
			'state' => $this->state,
			'createdAt' => $this->createdAt,
			'sentAt' => $this->sentAt,
		];
	}
}
