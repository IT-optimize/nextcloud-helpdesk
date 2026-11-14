<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Db\HelpRequest;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Who sees which requests. Everyone sees their own. The members of the "IT
 * contact" group an admin chooses (for example the office manager) see
 * everyone's, and can write in them and mark them solved. Admins are not
 * included on their own: requests can be personal.
 */
class AccessService {
	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
	) {
	}

	public function contactGroup(): ?string {
		$group = $this->appConfig->getValueString(Application::APP_ID, 'contact_group', '');
		return $group !== '' && $this->groupManager->groupExists($group) ? $group : null;
	}

	/** @throws ValidationException */
	public function setContactGroup(?string $group): void {
		$group = trim((string)$group);
		if ($group !== '' && !$this->groupManager->groupExists($group)) {
			throw new ValidationException('The group {name} does not exist', ['name' => $group]);
		}
		$this->appConfig->setValueString(Application::APP_ID, 'contact_group', $group);
	}

	/** Whether this person is one of the IT contacts who see everyone's requests. */
	public function isContact(IUser $user): bool {
		$group = $this->contactGroup();
		return $group !== null && $this->groupManager->isInGroup($user->getUID(), $group);
	}

	public function canSee(IUser $user, HelpRequest $request): bool {
		return $request->getUid() === $user->getUID() || $this->isContact($user);
	}
}
