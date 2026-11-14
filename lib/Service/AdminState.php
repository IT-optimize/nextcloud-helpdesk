<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

use OCP\IGroupManager;

/** What the admin settings show, for the page and after every change. */
class AdminState {
	public function __construct(
		private ProviderService $providers,
		private RequestService $requests,
		private AccessService $access,
		private IGroupManager $groupManager,
	) {
	}

	public function get(): array {
		$groups = [];
		foreach ($this->groupManager->search('', 200) as $group) {
			$groups[] = ['id' => $group->getGID(), 'name' => $group->getDisplayName()];
		}
		usort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
		return [
			'locked' => $this->providers->isLocked(),
			'active' => $this->providers->active(),
			'providers' => $this->providers->all(),
			'activeId' => $this->providers->activeId(),
			'unsent' => $this->requests->countUnsent(),
			'contactGroup' => $this->access->contactGroup(),
			'groups' => $groups,
		];
	}
}
