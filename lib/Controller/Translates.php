<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Controller;

use OCA\Helpdesk\Service\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;

/** Runs an action and turns a ValidationException into a 400 with a translated message. */
trait Translates {
	private IL10N $l;

	private function respond(callable $fn, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($fn(), $status);
		} catch (ValidationException $e) {
			$text = $this->l->t($e->getMessage());
			foreach ($e->getParameters() as $key => $value) {
				$text = str_replace('{' . $key . '}', (string)$value, $text);
			}
			return new DataResponse(['message' => $text], Http::STATUS_BAD_REQUEST);
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => $this->l->t('This request does not exist, or you cannot see it')], Http::STATUS_NOT_FOUND);
		}
	}
}
