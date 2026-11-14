<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

use OCA\Helpdesk\AppInfo\Application;
use OCA\Helpdesk\Db\Entry;
use OCA\Helpdesk\Db\EntryMapper;
use OCA\Helpdesk\Db\HelpRequest;
use OCA\Helpdesk\Db\HelpRequestMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * Requests for help: stored first, then emailed to the provider. A request
 * that cannot be sent stays and is tried again by SendRequestsJob; after three
 * failed tries the admins get a notification.
 *
 * After the request, its history holds messages to the provider (emailed in
 * the same thread, sent and retried the same way, but never before the request
 * itself), notes that stay in the company, and changes of status.
 */
class RequestService {
	public const MAX_FILES = 5;
	public const MAX_TOTAL_BYTES = 10 * 1024 * 1024;
	public const MAX_TEXT = 20000;
	private const TELL_ADMINS_AFTER = 3;

	private IAppData $appData;

	public function __construct(
		private HelpRequestMapper $mapper,
		private EntryMapper $entries,
		private ProviderService $providers,
		private AccessService $access,
		private IMailer $mailer,
		IAppDataFactory $appDataFactory,
		private ITimeFactory $time,
		private IFactory $l10nFactory,
		private Defaults $defaults,
		private IURLGenerator $url,
		private INotificationManager $notifications,
		private IGroupManager $groupManager,
		private LoggerInterface $logger,
	) {
		$this->appData = $appDataFactory->get(Application::APP_ID);
	}

	/**
	 * @param 'mine'|'all' $scope everyone's only for the IT contacts
	 * @return HelpRequest[]
	 */
	public function list(IUser $user, string $scope = 'mine', ?string $status = null): array {
		$everyone = $scope === 'all' && $this->access->isContact($user);
		$status = in_array($status, [HelpRequest::OPEN, HelpRequest::SOLVED], true) ? $status : null;
		return $this->mapper->findList($everyone ? null : $user->getUID(), $status);
	}

	/**
	 * A request this person may see.
	 *
	 * @throws DoesNotExistException also when it is someone else's
	 */
	public function get(IUser $user, int $id): HelpRequest {
		$request = $this->mapper->findById($id);
		if (!$this->access->canSee($user, $request)) {
			throw new DoesNotExistException('Not found');
		}
		return $request;
	}

	/** @return Entry[] */
	public function history(HelpRequest $request): array {
		return $this->entries->findForRequest($request->getId());
	}

	/**
	 * Stores a request and tries to send it right away.
	 *
	 * @param array{subject?: mixed, body?: mixed, urgent?: mixed, replyEmail?: mixed} $data
	 * @param list<array{name: string, tmp_name: string, size: int, error: int, type?: string}> $uploads
	 * @throws ValidationException
	 */
	public function create(IUser $user, array $data, array $uploads = []): HelpRequest {
		$provider = $this->providers->active();
		if ($provider === null) {
			throw new ValidationException('Helpdesk has no receiver yet. Ask your administrator to set it up.');
		}
		$subject = trim(preg_replace('/\s+/u', ' ', (string)($data['subject'] ?? '')) ?? '');
		$body = self::text($data['body'] ?? '');
		$replyEmail = strtolower(trim((string)($data['replyEmail'] ?? '')));
		if ($subject === '') {
			throw new ValidationException('Write what the problem is about');
		}
		if ($body === '') {
			throw new ValidationException('Describe the problem');
		}
		if (!filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
			throw new ValidationException('Enter the email address the answer should go to');
		}
		$files = $this->checkUploads($uploads);
		$now = $this->time->getTime();

		$request = new HelpRequest();
		$request->setUid($user->getUID());
		$request->setReplyEmail($replyEmail);
		$request->setReplyName(mb_substr($user->getDisplayName(), 0, 255));
		$request->setSubject(mb_substr($subject, 0, 200));
		$request->setBody($body);
		$request->setUrgent(filter_var($data['urgent'] ?? false, FILTER_VALIDATE_BOOLEAN));
		$request->setProviderName($provider['name']);
		$request->setProviderEmail($provider['email']);
		$request->setState(HelpRequest::PENDING);
		$request->setStatus(HelpRequest::OPEN);
		$request->setAttempts(0);
		$request->setMessageId(RequestMail::newMessageId($this->sender()));
		$request->setCreatedAt($now);
		$request->setUpdatedAt($now);
		$request = $this->mapper->insert($request);

		if ($files !== []) {
			$request->setFiles(json_encode($this->storeFiles($request->getId(), $files), JSON_THROW_ON_ERROR));
			$request = $this->mapper->update($request);
		}
		return $this->trySend($request);
	}

	/**
	 * A note in the request's history. It stays in the company and is never emailed.
	 *
	 * @throws ValidationException
	 */
	public function addNote(IUser $user, HelpRequest $request, mixed $body): Entry {
		$text = self::text($body);
		if ($text === '') {
			throw new ValidationException('Write the note first');
		}
		$entry = $this->entry($user, $request, Entry::NOTE, $text);
		$this->touch($request);
		return $entry;
	}

	/**
	 * A message to the provider, emailed in the request's thread. Writing on a
	 * solved request opens it again.
	 *
	 * @throws ValidationException
	 */
	public function addMessage(IUser $user, HelpRequest $request, mixed $body): Entry {
		$text = self::text($body);
		if ($text === '') {
			throw new ValidationException('Write the message first');
		}
		if ($request->getStatus() === HelpRequest::SOLVED) {
			$this->setStatus($user, $request, HelpRequest::OPEN);
		}
		$entry = new Entry();
		$entry->setRequestId($request->getId());
		$entry->setUid($user->getUID());
		$entry->setAuthorName(mb_substr($user->getDisplayName(), 0, 255));
		$entry->setKind(Entry::MESSAGE);
		$entry->setBody($text);
		$entry->setCreatedAt($this->time->getTime());
		$entry->setMessageId(RequestMail::newMessageId($this->sender()));
		$entry->setState(HelpRequest::PENDING);
		$entry->setAttempts(0);
		$entry = $this->entries->insert($entry);
		$this->touch($request);
		// Never ahead of the request itself: until it is sent, the message waits for SendRequestsJob.
		return $request->getState() === HelpRequest::SENT ? $this->trySendEntry($entry, $request) : $entry;
	}

	/**
	 * Marks a request solved, or open again. The provider is not emailed: a
	 * "solved" email would only open their ticket again.
	 *
	 * @throws ValidationException
	 */
	public function setStatus(IUser $user, HelpRequest $request, mixed $status): HelpRequest {
		if (!in_array($status, [HelpRequest::OPEN, HelpRequest::SOLVED], true)) {
			throw new ValidationException('Unknown status');
		}
		if ($request->getStatus() === $status) {
			return $request;
		}
		$request->setStatus($status);
		$request->setSolvedAt($status === HelpRequest::SOLVED ? $this->time->getTime() : null);
		$this->entry($user, $request, $status === HelpRequest::SOLVED ? Entry::SOLVED : Entry::REOPENED, null);
		return $this->touch($request);
	}

	/** Sends every request and message whose next try is due; with $all also those waiting for a later try. */
	public function sendDue(bool $all = false): int {
		$sent = 0;
		$now = $all ? PHP_INT_MAX : $this->time->getTime();
		foreach ($this->mapper->findDue($now) as $request) {
			if ($this->trySend($request)->getState() === HelpRequest::SENT) {
				$sent++;
			}
		}
		foreach ($this->entries->findDue($now) as $entry) {
			try {
				$request = $this->mapper->findById($entry->getRequestId());
			} catch (DoesNotExistException) {
				continue;
			}
			if ($request->getState() !== HelpRequest::SENT) {
				continue; // waits until the request itself is through
			}
			if ($this->trySendEntry($entry, $request)->getState() === HelpRequest::SENT) {
				$sent++;
			}
		}
		return $sent;
	}

	public function countUnsent(): int {
		return $this->mapper->countUnsent() + $this->entries->countUnsent();
	}

	private function entry(IUser $user, HelpRequest $request, string $kind, ?string $body): Entry {
		$entry = new Entry();
		$entry->setRequestId($request->getId());
		$entry->setUid($user->getUID());
		$entry->setAuthorName(mb_substr($user->getDisplayName(), 0, 255));
		$entry->setKind($kind);
		$entry->setBody($body);
		$entry->setAttempts(0);
		$entry->setCreatedAt($this->time->getTime());
		return $this->entries->insert($entry);
	}

	private function touch(HelpRequest $request): HelpRequest {
		$request->setUpdatedAt($this->time->getTime());
		return $this->mapper->update($request);
	}

	private static function text(mixed $value): string {
		return mb_substr(trim(str_replace("\r\n", "\n", (string)(is_scalar($value) ? $value : ''))), 0, self::MAX_TEXT);
	}

	private function trySend(HelpRequest $request): HelpRequest {
		$request = $this->deliver($request, fn () => $this->buildMail($request), 'request ' . $request->getId(), $request->getProviderName());
		if ($request->getState() === HelpRequest::SENT) {
			$this->removeFiles($request);
		}
		return $request;
	}

	private function trySendEntry(Entry $entry, HelpRequest $request): Entry {
		return $this->deliver($entry, fn () => $this->buildMessageMail($entry, $request), 'message ' . $entry->getId(), $request->getProviderName());
	}

	/**
	 * Sends one email and records the outcome on the request or message: sent,
	 * or failed with the time of the next try (and the admins told after three).
	 *
	 * @template T of HelpRequest|Entry
	 * @param T $item
	 * @param callable(): IMessage $build
	 * @return T
	 */
	private function deliver(HelpRequest|Entry $item, callable $build, string $what, string $providerName): HelpRequest|Entry {
		$mapper = $item instanceof Entry ? $this->entries : $this->mapper;
		$object = $item instanceof Entry ? 'entry' : 'request';
		try {
			$failed = $this->mailer->send($build());
			if ($failed !== []) {
				throw new \RuntimeException('The mail server refused ' . implode(', ', $failed));
			}
			$item->setState(HelpRequest::SENT);
			$item->setSentAt($this->time->getTime());
			$item->setLastError(null);
			$item->setNextTryAt(null);
			$item = $mapper->update($item);
			if ($item->getAdminsTold()) {
				$this->dismissAdminNotification($object, $item->getId());
			}
			return $item;
		} catch (\Throwable $e) {
			$attempts = $item->getAttempts() + 1;
			$this->logger->warning('Helpdesk could not send ' . $what . ' (try ' . $attempts . ')', ['exception' => $e]);
			$item->setState(HelpRequest::FAILED);
			$item->setAttempts($attempts);
			$item->setLastError(mb_substr($e->getMessage(), 0, 500));
			$item->setNextTryAt($this->time->getTime() + RequestMail::retryDelay($attempts));
			if ($attempts >= self::TELL_ADMINS_AFTER && !$item->getAdminsTold()) {
				$this->tellAdmins($object, $item->getId(), $providerName, (string)$item->getLastError());
				$item->setAdminsTold(true);
			}
			return $mapper->update($item);
		}
	}

	private function buildMail(HelpRequest $request): IMessage {
		$l = $this->l10nFactory->get(Application::APP_ID);
		$files = $request->getFileList();
		$mail = $this->baseMail($request, $request->getReplyName() ?: $request->getUid());
		$mail->setSubject(($request->getUrgent() ? $l->t('Urgent') . ': ' : '') . $request->getSubject());
		$mail->setPlainBody($this->body($request->getBody(), $request, $request->getReplyName(), $request->getUid(), (bool)$request->getUrgent(), array_map(fn (array $f) => $f['name'], $files)));
		foreach ($files as $file) {
			$content = $this->folder()->getFile($file['file'])->getContent();
			$mail->attach($this->mailer->createAttachment($content, $file['name'], $file['mime']));
		}
		$this->setHeaders($mail, $request->getMessageId(), RequestMail::threadHeaders([]));
		return $mail;
	}

	/** A message after the request: same receiver and Reply-To, in the request's thread. */
	private function buildMessageMail(Entry $entry, HelpRequest $request): IMessage {
		$name = $entry->getAuthorName() ?: $entry->getUid();
		$mail = $this->baseMail($request, $name);
		$mail->setSubject(RequestMail::replySubject($request->getSubject()));
		$mail->setPlainBody(RequestMail::followUpBody((string)$entry->getBody()));
		$earlier = array_merge([(string)$request->getMessageId()], $this->entries->sentMessageIds($request->getId()));
		$this->setHeaders($mail, $entry->getMessageId(), RequestMail::threadHeaders($earlier));
		return $mail;
	}

	/**
	 * To the receiver of the request, from this Nextcloud's no-reply address in
	 * the writer's name; answers go to the address the request named.
	 */
	private function baseMail(HelpRequest $request, string $writer): IMessage {
		$l = $this->l10nFactory->get(Application::APP_ID);
		$mail = $this->mailer->createMessage();
		$mail->setTo([$request->getProviderEmail() => $request->getProviderName()]);
		$mail->setFrom([$this->sender() => $l->t('%1$s (%2$s)', [$writer, $this->defaults->getName()])]);
		$mail->setReplyTo([$request->getReplyEmail() => $request->getReplyName() ?: $request->getUid()]);
		return $mail;
	}

	/** @param list<string> $files */
	private function body(string $text, HelpRequest $request, ?string $name, string $uid, bool $urgent, array $files): string {
		$l = $this->l10nFactory->get(Application::APP_ID);
		return RequestMail::body($text, [
			'name' => $l->t('Name'),
			'email' => $l->t('Email'),
			'user' => $l->t('Nextcloud user'),
			'organisation' => $l->t('From'),
			'urgent' => $l->t('Marked as urgent'),
			'files' => $l->t('Files'),
		], [
			'name' => $name,
			'email' => $request->getReplyEmail(),
			'uid' => $uid,
			'organisation' => $this->defaults->getName(),
			'server' => rtrim($this->url->getAbsoluteURL('/'), '/'),
			'urgent' => $urgent,
			'files' => $files,
		]);
	}

	private function sender(): string {
		return \OCP\Util::getDefaultEmailAddress('noreply');
	}

	/**
	 * Adds the X-Nextcloud-Helpdesk header and the thread headers. IMessage
	 * cannot set headers, so this reaches the Symfony email behind it when
	 * there is one; without it the marker line at the end of the text still
	 * marks the request, but messages after it are not threaded.
	 *
	 * @param array{inReplyTo: ?string, references: list<string>} $thread
	 */
	private function setHeaders(IMessage $mail, ?string $messageId, array $thread): void {
		$email = method_exists($mail, 'getSymfonyEmail') ? $mail->getSymfonyEmail() : null;
		if (!$email instanceof Email) {
			return;
		}
		try {
			$headers = $email->getHeaders();
			$headers->addTextHeader('X-Nextcloud-Helpdesk', '1');
			if ($messageId !== null && $messageId !== '') {
				$headers->remove('Message-ID');
				$headers->addIdHeader('Message-ID', $messageId);
			}
			if ($thread['inReplyTo'] !== null) {
				$headers->addIdHeader('In-Reply-To', $thread['inReplyTo']);
				$headers->addIdHeader('References', $thread['references']);
			}
		} catch (\Throwable $e) {
			$this->logger->info('Helpdesk could not set the headers of a request email', ['exception' => $e]);
		}
	}

	/**
	 * @return list<array{name: string, tmp_name: string, size: int, mime: string}>
	 * @throws ValidationException
	 */
	private function checkUploads(array $uploads): array {
		$files = [];
		$total = 0;
		foreach ($uploads as $upload) {
			if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
				throw new ValidationException('A file could not be uploaded. Try again, or leave it out.');
			}
			$total += (int)$upload['size'];
			$files[] = [
				'name' => RequestMail::cleanFilename((string)$upload['name']),
				'tmp_name' => (string)$upload['tmp_name'],
				'size' => (int)$upload['size'],
				'mime' => preg_match('~^[\w.+-]+/[\w.+-]+$~', (string)($upload['type'] ?? '')) ? (string)$upload['type'] : 'application/octet-stream',
			];
		}
		if (count($files) > self::MAX_FILES) {
			throw new ValidationException('At most {count} files', ['count' => self::MAX_FILES]);
		}
		if ($total > self::MAX_TOTAL_BYTES) {
			throw new ValidationException('The files are too large together (at most {size} MB)', ['size' => self::MAX_TOTAL_BYTES / 1024 / 1024]);
		}
		return $files;
	}

	/** @return list<array{name: string, size: int, mime: string, file: string}> */
	private function storeFiles(int $requestId, array $files): array {
		$folder = $this->folder();
		$stored = [];
		foreach ($files as $i => $file) {
			$name = $requestId . '-' . $i;
			$folder->newFile($name, (string)file_get_contents($file['tmp_name']));
			$stored[] = ['name' => $file['name'], 'size' => $file['size'], 'mime' => $file['mime'], 'file' => $name];
		}
		return $stored;
	}

	/** Files are only kept until the request is sent: the provider has them then. */
	private function removeFiles(HelpRequest $request): void {
		foreach ($request->getFileList() as $file) {
			try {
				$this->folder()->getFile($file['file'])->delete();
			} catch (\Throwable) {
				// already gone
			}
		}
	}

	private function folder(): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder('requests');
		} catch (NotFoundException) {
			return $this->appData->newFolder('requests');
		}
	}

	private function tellAdmins(string $object, int $id, string $providerName, string $error): void {
		try {
			$notification = $this->notifications->createNotification();
			$notification->setApp(Application::APP_ID)
				->setDateTime(new \DateTime('@' . $this->time->getTime()))
				->setObject($object, (string)$id)
				->setSubject('send_failed', ['provider' => $providerName, 'error' => $error]);
			foreach ($this->groupManager->get('admin')?->getUsers() ?? [] as $admin) {
				$notification->setUser($admin->getUID());
				$this->notifications->notify($notification);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Helpdesk could not tell the admins about an unsent request', ['exception' => $e]);
		}
	}

	private function dismissAdminNotification(string $object, int $id): void {
		$notification = $this->notifications->createNotification();
		$notification->setApp(Application::APP_ID)->setObject($object, (string)$id);
		$this->notifications->markProcessed($notification);
	}
}
