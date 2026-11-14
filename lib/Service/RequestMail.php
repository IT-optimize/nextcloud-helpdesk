<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

/** The email a request becomes, and when to try again, without Nextcloud around it. */
final class RequestMail {
	/**
	 * Last line of every request. Ticket system for Nextcloud recognises it (and
	 * the X-Nextcloud-Helpdesk header) and files the ticket under the person in
	 * Reply-To. Never translate or change it.
	 */
	public const MARKER = 'Sent with Helpdesk for Nextcloud';

	/** Seconds until the next try after $attempts failed tries: 5 min, 15 min, 1 h, then every 3 h. */
	public static function retryDelay(int $attempts): int {
		return match (true) {
			$attempts <= 1 => 5 * 60,
			$attempts === 2 => 15 * 60,
			$attempts === 3 => 60 * 60,
			default => 3 * 60 * 60,
		};
	}

	/**
	 * The text of the email: what the person wrote, then who they are, so the
	 * provider knows where it comes from even without a ticket system.
	 *
	 * @param array{name: string, email: string, user: string, organisation: string, server: string, urgent: string, files: string} $labels translated labels
	 * @param array{name: ?string, email: string, uid: string, organisation: string, server: string, urgent: bool, files: list<string>} $facts
	 */
	public static function body(string $text, array $labels, array $facts): string {
		$lines = [
			$labels['name'] . ': ' . ($facts['name'] ?: $facts['uid']),
			$labels['email'] . ': ' . $facts['email'],
			$labels['user'] . ': ' . $facts['uid'],
			$labels['organisation'] . ': ' . $facts['organisation'] . ($facts['server'] !== '' ? ' (' . $facts['server'] . ')' : ''),
		];
		if ($facts['urgent']) {
			$lines[] = $labels['urgent'];
		}
		if ($facts['files'] !== []) {
			$lines[] = $labels['files'] . ': ' . implode(', ', $facts['files']);
		}
		return rtrim($text) . "\n\n" . str_repeat('—', 20) . "\n" . implode("\n", $lines) . "\n\n" . self::MARKER . "\n";
	}

	/**
	 * The text of a message after the request: only what the person wrote and
	 * the marker. Who they are is in the request (and in From and Reply-To),
	 * so it is not repeated in every message of the thread.
	 */
	public static function followUpBody(string $text): string {
		return rtrim($text) . "\n\n" . self::MARKER . "\n";
	}

	/**
	 * A new Message-ID (without the angle brackets) under the sender's domain,
	 * so messages after the request can refer to it and join its thread.
	 */
	public static function newMessageId(string $senderAddress): string {
		$domain = strtolower(substr(strrchr($senderAddress, '@') ?: '@localhost', 1));
		if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
			$domain = 'localhost';
		}
		return 'helpdesk.' . bin2hex(random_bytes(12)) . '@' . $domain;
	}

	/**
	 * In-Reply-To and References for a message in a request's thread: the
	 * request's own Message-ID first, then the messages sent after it.
	 *
	 * @param list<string> $earlier Message-IDs already in the thread, oldest first
	 * @return array{inReplyTo: ?string, references: list<string>}
	 */
	public static function threadHeaders(array $earlier): array {
		$ids = array_values(array_unique(array_filter($earlier, fn ($id) => is_string($id) && $id !== '')));
		return ['inReplyTo' => $ids === [] ? null : $ids[count($ids) - 1], 'references' => $ids];
	}

	/** The subject of a message after the request: "Re: " once. */
	public static function replySubject(string $subject): string {
		return preg_match('/^re:\s/i', $subject) ? $subject : 'Re: ' . $subject;
	}

	/** A file name safe to store and to send: no paths, no control characters. */
	public static function cleanFilename(string $name): string {
		$name = basename(str_replace('\\', '/', $name));
		$name = trim(preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', '_', $name) ?? '', " .\t");
		return $name !== '' ? mb_substr($name, -120) : 'file';
	}
}
