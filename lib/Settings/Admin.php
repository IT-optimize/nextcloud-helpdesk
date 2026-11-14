<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Settings;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Service\AdminState;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

/** Administration settings → Helpdesk: who receives the requests, and who sees them all. */
class Admin implements ISettings {
	public function __construct(
		private AdminState $state,
		private IInitialState $initialState,
		private IUserSession $userSession,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('settings', $this->state->get() + [
			'myEmail' => $this->userSession->getUser()?->getEMailAddress() ?? '',
		]);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
