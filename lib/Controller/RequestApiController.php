<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Controller;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Db\HelpRequest;
use OCA\Helpdesk\Service\RequestService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Requests: a person's own, or everyone's for the IT contacts. Each request
 * has a history of messages to the provider, notes and status changes.
 */
class RequestApiController extends OCSController {
	use Translates;

	public function __construct(
		IRequest $request,
		IL10N $l,
		private IUserSession $userSession,
		private RequestService $requests,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->l = $l;
	}

	/**
	 * @param 'mine'|'all' $scope
	 * @param 'open'|'solved'|'' $status empty for both
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests')]
	public function index(string $scope = 'mine', string $status = ''): DataResponse {
		return $this->respond(fn () => ['requests' => $this->requests->list($this->user(), $scope, $status ?: null)]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests/{id}', requirements: ['id' => '\d+'])]
	public function show(int $id): DataResponse {
		return $this->respond(fn () => $this->details($this->requests->get($this->user(), $id)));
	}

	/**
	 * Multipart form: subject, body, urgent, replyEmail and files[] (optional).
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 3600)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests')]
	public function create(): DataResponse {
		return $this->respond(function () {
			$request = $this->requests->create($this->user(), [
				'subject' => $this->request->getParam('subject'),
				'body' => $this->request->getParam('body'),
				'urgent' => $this->request->getParam('urgent'),
				'replyEmail' => $this->request->getParam('replyEmail'),
			], $this->uploads());
			return ['request' => $request];
		}, Http::STATUS_CREATED);
	}

	/** A message to the provider, emailed in the request's thread. */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 40, period: 3600)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/messages', requirements: ['id' => '\d+'])]
	public function message(int $id, string $body = ''): DataResponse {
		return $this->respond(function () use ($id, $body) {
			$request = $this->requests->get($this->user(), $id);
			$this->requests->addMessage($this->user(), $request, $body);
			return $this->details($this->requests->get($this->user(), $id));
		}, Http::STATUS_CREATED);
	}

	/** A note that stays in the company. */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 100, period: 3600)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/notes', requirements: ['id' => '\d+'])]
	public function note(int $id, string $body = ''): DataResponse {
		return $this->respond(function () use ($id, $body) {
			$request = $this->requests->get($this->user(), $id);
			$this->requests->addNote($this->user(), $request, $body);
			return $this->details($this->requests->get($this->user(), $id));
		}, Http::STATUS_CREATED);
	}

	/** @param 'open'|'solved' $status */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/requests/{id}/status', requirements: ['id' => '\d+'])]
	public function status(int $id, string $status = ''): DataResponse {
		return $this->respond(function () use ($id, $status) {
			$request = $this->requests->get($this->user(), $id);
			return $this->details($this->requests->setStatus($this->user(), $request, $status));
		});
	}

	private function details(HelpRequest $request): array {
		return [
			'request' => $request,
			'history' => $this->requests->history($request),
		];
	}

	private function user(): IUser {
		return $this->userSession->getUser() ?? throw new \RuntimeException('Not logged in');
	}

	/** files[] as a list of single uploads, whichever shape PHP gave them. */
	private function uploads(): array {
		$raw = $this->request->getUploadedFile('files');
		if (!is_array($raw) || !isset($raw['name'])) {
			return [];
		}
		if (!is_array($raw['name'])) {
			return [$raw];
		}
		$list = [];
		foreach (array_keys($raw['name']) as $i) {
			$list[] = [
				'name' => $raw['name'][$i],
				'type' => $raw['type'][$i] ?? '',
				'tmp_name' => $raw['tmp_name'][$i] ?? '',
				'error' => $raw['error'][$i] ?? UPLOAD_ERR_NO_FILE,
				'size' => $raw['size'][$i] ?? 0,
			];
		}
		return $list;
	}
}
