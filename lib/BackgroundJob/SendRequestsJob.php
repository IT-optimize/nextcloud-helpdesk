<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\BackgroundJob;

use OCA\Helpdesk\Service\RequestService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/** Tries again to send the requests that could not be emailed yet. */
class SendRequestsJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private RequestService $requests,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		try {
			$this->requests->sendDue();
		} catch (\Throwable $e) {
			$this->logger->error('Helpdesk could not send waiting requests', ['exception' => $e]);
		}
	}
}
