<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Service;

/**
 * The rules for providers (who receives the requests), without Nextcloud
 * around them, so they can be tested on their own.
 *
 * A provider is ['id' => string, 'name', 'email', 'phone', 'website', 'hours',
 * 'note', 'contacts' => list of ['name', 'role', 'phone', 'email']]. Requests
 * always go to the provider's own email; the contacts are only shown.
 */
final class Providers {
	public const FIELDS = ['name' => 100, 'email' => 255, 'phone' => 50, 'website' => 255, 'hours' => 200, 'note' => 500];
	public const CONTACT_FIELDS = ['name' => 100, 'role' => 100, 'phone' => 50, 'email' => 255];
	public const MAX = 10;
	public const MAX_CONTACTS = 10;

	/**
	 * A provider as an admin entered it, cleaned up.
	 *
	 * @throws ValidationException
	 */
	public static function clean(array $input, string $id): array {
		$provider = ['id' => $id] + self::fields($input, self::FIELDS);
		$provider['email'] = strtolower($provider['email']);
		if ($provider['name'] === '') {
			throw new ValidationException('Every provider needs a name');
		}
		if (!filter_var($provider['email'], FILTER_VALIDATE_EMAIL)) {
			throw new ValidationException('{name} needs a valid email address', ['name' => $provider['name']]);
		}
		if ($provider['website'] !== '') {
			if (!preg_match('~^https?://~i', $provider['website'])) {
				$provider['website'] = 'https://' . $provider['website'];
			}
			if (!filter_var($provider['website'], FILTER_VALIDATE_URL)) {
				throw new ValidationException('The website of {name} is not a web address', ['name' => $provider['name']]);
			}
		}
		$provider['contacts'] = self::cleanContacts($input['contacts'] ?? [], $provider['name']);
		return $provider;
	}

	/**
	 * The people at a provider, as shown on the contact card. Rows left
	 * completely empty are dropped.
	 *
	 * @return list<array{name: string, role: string, phone: string, email: string}>
	 * @throws ValidationException
	 */
	public static function cleanContacts(mixed $input, string $providerName): array {
		if (!is_array($input)) {
			return [];
		}
		$contacts = [];
		foreach (array_values($input) as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$contact = self::fields($entry, self::CONTACT_FIELDS);
			if (implode('', $contact) === '') {
				continue;
			}
			$contact['email'] = strtolower($contact['email']);
			if ($contact['name'] === '') {
				throw new ValidationException('Every contact person at {name} needs a name', ['name' => $providerName]);
			}
			if ($contact['email'] !== '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
				throw new ValidationException('{name} needs a valid email address', ['name' => $contact['name']]);
			}
			$contacts[] = $contact;
		}
		if (count($contacts) > self::MAX_CONTACTS) {
			throw new ValidationException('At most {max} contact people per provider', ['max' => self::MAX_CONTACTS]);
		}
		return $contacts;
	}

	/** @param array<string, int> $fields name => max length */
	private static function fields(array $input, array $fields): array {
		$clean = [];
		foreach ($fields as $field => $max) {
			$value = trim((string)(is_scalar($input[$field] ?? null) ? $input[$field] : ''));
			$clean[$field] = mb_substr(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $value) ?? '', 0, $max);
		}
		return $clean;
	}

	/** Whether config.php names a provider ('helpdesk' => ['provider' => [...]]), usable or not. */
	public static function isSetInSystemConfig(mixed $config): bool {
		return is_array($config) && is_array($config['provider'] ?? null);
	}

	/**
	 * The provider set in config.php, which locks the receiver. Null when
	 * there is none or it is incomplete.
	 */
	public static function fromSystemConfig(mixed $config): ?array {
		if (!is_array($config) || !is_array($config['provider'] ?? null)) {
			return null;
		}
		try {
			return self::clean($config['provider'], 'system');
		} catch (ValidationException) {
			return null;
		}
	}

	/**
	 * The list an admin saves: every entry cleaned, ids kept or made, at most MAX.
	 *
	 * @param list<array> $input
	 * @return list<array>
	 * @throws ValidationException
	 */
	public static function cleanList(array $input): array {
		if (count($input) > self::MAX) {
			throw new ValidationException('At most {max} providers', ['max' => self::MAX]);
		}
		$list = [];
		$ids = [];
		foreach (array_values($input) as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$id = preg_match('/^[a-z0-9]{1,16}$/', (string)($entry['id'] ?? '')) && !in_array($entry['id'], $ids, true)
				? (string)$entry['id']
				: bin2hex(random_bytes(6));
			$ids[] = $id;
			$list[] = self::clean($entry, $id);
		}
		return $list;
	}

	/** The active provider: the chosen one, or the first when the choice is gone. */
	public static function pick(array $list, ?string $activeId): ?array {
		foreach ($list as $provider) {
			if ($provider['id'] === $activeId) {
				return $provider;
			}
		}
		return $list[0] ?? null;
	}
}
