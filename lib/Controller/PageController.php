<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Controller;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Service\AccessService;
use OCA\Helpdesk\Service\ProviderService;
use OCA\Helpdesk\Service\RequestService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

/** The page where everyone in the Nextcloud asks for help. */
class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IInitialState $initialState,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ProviderService $providers,
		private RequestService $requests,
		private AccessService $access,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$user = $this->userSession->getUser();
		$provider = $this->providers->active();
		$this->initialState->provideInitialState('page', [
			'provider' => $provider === null ? null : array_diff_key($provider, ['id' => true]),
			'isAdmin' => $user !== null && $this->groupManager->isAdmin($user->getUID()),
			'isContact' => $user !== null && $this->access->isContact($user),
			'me' => ['uid' => $user?->getUID() ?? '', 'name' => $user?->getDisplayName() ?? '', 'email' => $user?->getEMailAddress() ?? ''],
			'requests' => $user !== null ? $this->requests->list($user) : [],
			'maxFiles' => RequestService::MAX_FILES,
			'maxBytes' => RequestService::MAX_TOTAL_BYTES,
		]);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
