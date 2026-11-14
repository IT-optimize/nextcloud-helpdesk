<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

use OCA\Helpdesk\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IConfig;

/**
 * Who receives the requests.
 *
 * Normally the admin keeps a list of providers and picks the one that receives
 * requests. A provider set in config.php locks the receiver: the settings then
 * show it read-only and nothing can send elsewhere. Nextcloud partners use this
 * in the images they deliver (see the README).
 */
class ProviderService {
	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * Locked as soon as config.php names a provider, even an incomplete one:
	 * a typo there must not open the receiver up for change.
	 */
	public function isLocked(): bool {
		return Providers::isSetInSystemConfig($this->systemConfig());
	}

	/** The provider that receives requests now, or null when none is set up (or the locked one is incomplete). */
	public function active(): ?array {
		if ($this->isLocked()) {
			return Providers::fromSystemConfig($this->systemConfig());
		}
		return Providers::pick($this->all(), $this->activeId());
	}

	/** @return list<array> the providers the admin entered (empty when locked) */
	public function all(): array {
		if ($this->isLocked()) {
			return [];
		}
		$list = json_decode($this->appConfig->getValueString(Application::APP_ID, 'providers', '[]'), true);
		return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
	}

	public function activeId(): ?string {
		$id = $this->appConfig->getValueString(Application::APP_ID, 'active_provider', '');
		return $id !== '' ? $id : null;
	}

	/**
	 * @param list<array> $providers
	 * @throws ValidationException
	 */
	public function save(array $providers, ?string $activeId): void {
		if ($this->isLocked()) {
			throw new ValidationException('The receiver is set in the server configuration and cannot be changed here');
		}
		$list = Providers::cleanList($providers);
		$active = Providers::pick($list, $activeId);
		$this->appConfig->setValueString(Application::APP_ID, 'providers', json_encode($list, JSON_THROW_ON_ERROR));
		$this->appConfig->setValueString(Application::APP_ID, 'active_provider', $active['id'] ?? '');
	}

	private function systemConfig(): mixed {
		return $this->config->getSystemValue('helpdesk', null);
	}
}
