<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Controller;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Service\AccessService;
use OCA\Helpdesk\Service\AdminState;
use OCA\Helpdesk\Service\ProviderService;
use OCA\Helpdesk\Service\RequestService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/** Admin only (no #[NoAdminRequired]): who receives the requests, and who sees them all. */
class SettingsApiController extends OCSController {
	use Translates;

	public function __construct(
		IRequest $request,
		IL10N $l,
		private IUserSession $userSession,
		private ProviderService $providers,
		private RequestService $requests,
		private AccessService $access,
		private AdminState $state,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l = $l;
	}

	#[ApiRoute(verb: 'GET', url: '/api/v1/settings')]
	public function show(): DataResponse {
		return $this->respond(fn () => $this->state->get());
	}

	/** @param list<array> $providers */
	#[ApiRoute(verb: 'PUT', url: '/api/v1/settings')]
	public function update(array $providers = [], ?string $activeId = null): DataResponse {
		return $this->respond(function () use ($providers, $activeId) {
			$this->providers->save($providers, $activeId);
			return $this->state->get();
		});
	}

	/** The group whose members see everyone's requests; empty for none. */
	#[ApiRoute(verb: 'PUT', url: '/api/v1/settings/contact-group')]
	public function contactGroup(string $group = ''): DataResponse {
		return $this->respond(function () use ($group) {
			$this->access->setContactGroup($group);
			return $this->state->get();
		});
	}

	/** Sends every unsent request now, without waiting for the next try. */
	#[ApiRoute(verb: 'POST', url: '/api/v1/settings/retry')]
	public function retry(): DataResponse {
		return $this->respond(fn () => ['sent' => $this->requests->sendDue(true)] + $this->state->get());
	}

	/** Sends a request from the admin to the receiver, to see that it arrives. */
	#[ApiRoute(verb: 'POST', url: '/api/v1/settings/test')]
	public function test(string $replyEmail = ''): DataResponse {
		return $this->respond(function () use ($replyEmail) {
			$user = $this->userSession->getUser() ?? throw new \RuntimeException('Not logged in');
			$request = $this->requests->create($user, [
				'subject' => $this->l->t('Test: Helpdesk is set up'),
				'body' => $this->l->t('This is a test from the Helpdesk app. Requests from this Nextcloud will arrive like this one. No reply is needed.'),
				'replyEmail' => $replyEmail,
			]);
			return ['request' => $request] + $this->state->get();
		}, Http::STATUS_CREATED);
	}
}
