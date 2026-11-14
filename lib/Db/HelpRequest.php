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
 * A request for help, as written by one person.
 *
 * "state" is the delivery of the email (pending, sent, failed); "status" is
 * whether the problem is solved, as the people in the company see it.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getReplyEmail()
 * @method void setReplyEmail(string $email)
 * @method ?string getReplyName()
 * @method void setReplyName(?string $name)
 * @method string getSubject()
 * @method void setSubject(string $subject)
 * @method string getBody()
 * @method void setBody(string $body)
 * @method ?bool getUrgent()
 * @method void setUrgent(?bool $urgent)
 * @method ?string getFiles()
 * @method void setFiles(?string $files)
 * @method string getProviderName()
 * @method void setProviderName(string $name)
 * @method string getProviderEmail()
 * @method void setProviderEmail(string $email)
 * @method string getState()
 * @method void setState(string $state)
 * @method int getAttempts()
 * @method void setAttempts(int $attempts)
 * @method ?string getLastError()
 * @method void setLastError(?string $error)
 * @method ?int getNextTryAt()
 * @method void setNextTryAt(?int $time)
 * @method ?bool getAdminsTold()
 * @method void setAdminsTold(?bool $told)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $time)
 * @method ?int getSentAt()
 * @method void setSentAt(?int $time)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?int getSolvedAt()
 * @method void setSolvedAt(?int $time)
 * @method ?string getMessageId()
 * @method void setMessageId(?string $id)
 * @method ?int getUpdatedAt()
 * @method void setUpdatedAt(?int $time)
 */
class HelpRequest extends Entity implements JsonSerializable {
	public const PENDING = 'pending';
	public const SENT = 'sent';
	public const FAILED = 'failed';

	public const OPEN = 'open';
	public const SOLVED = 'solved';

	protected $uid;
	protected $replyEmail;
	protected $replyName;
	protected $subject;
	protected $body;
	protected $urgent;
	protected $files;
	protected $providerName;
	protected $providerEmail;
	protected $state;
	protected $attempts;
	protected $lastError;
	protected $nextTryAt;
	protected $adminsTold;
	protected $createdAt;
	protected $sentAt;
	protected $status;
	protected $solvedAt;
	protected $messageId;
	protected $updatedAt;

	public function __construct() {
		$this->addType('urgent', Types::BOOLEAN);
		$this->addType('attempts', Types::INTEGER);
		$this->addType('nextTryAt', Types::BIGINT);
		$this->addType('adminsTold', Types::BOOLEAN);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('sentAt', Types::BIGINT);
		$this->addType('solvedAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	/** @return list<array{name: string, size: int, mime: string, file: string}> */
	public function getFileList(): array {
		$list = json_decode($this->files ?? '[]', true);
		return is_array($list) ? array_values($list) : [];
	}

	/** What the people in the company see: never the error details or file paths. */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uid' => $this->uid,
			'name' => $this->replyName ?: $this->uid,
			'replyEmail' => $this->replyEmail,
			'subject' => $this->subject,
			'body' => $this->body,
			'urgent' => (bool)$this->urgent,
			'files' => array_map(fn (array $f) => ['name' => $f['name'], 'size' => $f['size']], $this->getFileList()),
			'providerName' => $this->providerName,
			'state' => $this->state,
			'status' => $this->status ?? self::OPEN,
			'createdAt' => $this->createdAt,
			'sentAt' => $this->sentAt,
			'solvedAt' => $this->solvedAt,
			'updatedAt' => $this->updatedAt ?? $this->createdAt,
		];
	}
}
