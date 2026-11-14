<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

/**
 * Something the person can fix. The message is an English text that has a
 * Danish translation, with {placeholders} filled from getParameters().
 */
class ValidationException extends \RuntimeException {
	/** @param array<string, string|int> $parameters */
	public function __construct(string $message, private array $parameters = []) {
		parent::__construct($message);
	}

	/** @return array<string, string|int> */
	public function getParameters(): array {
		return $this->parameters;
	}
}
