<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Tests\Unit;

use OCA\Helpdesk\Service\Providers;
use OCA\Helpdesk\Service\ValidationException;
use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase {
	public function testCleanTrimsLowercasesAndAddsHttps(): void {
		$provider = Providers::clean([
			'name' => '  IT-Hjælpen ',
			'email' => 'Support@IT-Hjaelpen.dk',
			'website' => 'it-hjaelpen.dk',
			'phone' => '+45 12 34 56 78',
			'extra' => 'ignored',
		], 'a1');
		$this->assertSame('IT-Hjælpen', $provider['name']);
		$this->assertSame('support@it-hjaelpen.dk', $provider['email']);
		$this->assertSame('https://it-hjaelpen.dk', $provider['website']);
		$this->assertSame('a1', $provider['id']);
		$this->assertArrayNotHasKey('extra', $provider);
	}

	public function testNameAndValidEmailAreRequired(): void {
		$this->expectException(ValidationException::class);
		Providers::clean(['name' => 'IT', 'email' => 'not an address'], 'a1');
	}

	public function testAMissingNameIsRefused(): void {
		$this->expectException(ValidationException::class);
		Providers::clean(['name' => ' ', 'email' => 'a@b.dk'], 'a1');
	}

	public function testSystemConfigLocksEvenWhenIncomplete(): void {
		$broken = ['provider' => ['name' => 'Co', 'email' => 'typo']];
		$this->assertTrue(Providers::isSetInSystemConfig($broken));
		$this->assertNull(Providers::fromSystemConfig($broken));

		$good = ['provider' => ['name' => 'IT Firma', 'email' => 'support@it-firma.example']];
		$this->assertSame('support@it-firma.example', Providers::fromSystemConfig($good)['email']);

		$this->assertFalse(Providers::isSetInSystemConfig(null));
		$this->assertFalse(Providers::isSetInSystemConfig(['other' => true]));
	}

	public function testListKeepsGoodIdsAndReplacesBadOrDoubleOnes(): void {
		$list = Providers::cleanList([
			['id' => 'abc', 'name' => 'A', 'email' => 'a@a.dk'],
			['id' => 'abc', 'name' => 'B', 'email' => 'b@b.dk'],
			['id' => '../x', 'name' => 'C', 'email' => 'c@c.dk'],
		]);
		$this->assertSame('abc', $list[0]['id']);
		$this->assertNotSame('abc', $list[1]['id']);
		$this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $list[2]['id']);
	}

	public function testContactsAreCleanedAndEmptyRowsDropped(): void {
		$provider = Providers::clean([
			'name' => 'IT', 'email' => 'it@it.dk',
			'contacts' => [
				['name' => ' Anna ', 'role' => 'Tekniker', 'phone' => '12 34', 'email' => 'R@IT.dk', 'x' => 'y'],
				['name' => '', 'role' => '', 'phone' => '', 'email' => ''],
				'junk',
			],
		], 'a1');
		$this->assertSame([['name' => 'Anna', 'role' => 'Tekniker', 'phone' => '12 34', 'email' => 'r@it.dk']], $provider['contacts']);
		$this->assertSame([], Providers::clean(['name' => 'IT', 'email' => 'it@it.dk'], 'a1')['contacts']);
	}

	public function testAContactNeedsANameAndAValidEmailIfAny(): void {
		$this->expectException(ValidationException::class);
		Providers::cleanContacts([['role' => 'Tekniker']], 'IT');
	}

	public function testAContactsEmailMustBeValid(): void {
		$this->expectException(ValidationException::class);
		Providers::cleanContacts([['name' => 'Anna', 'email' => 'anna@']], 'IT');
	}

	public function testPickFallsBackToTheFirst(): void {
		$list = [['id' => 'a'], ['id' => 'b']];
		$this->assertSame('b', Providers::pick($list, 'b')['id']);
		$this->assertSame('a', Providers::pick($list, 'gone')['id']);
		$this->assertNull(Providers::pick([], 'a'));
	}
}
