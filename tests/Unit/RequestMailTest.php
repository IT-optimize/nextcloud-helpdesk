<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Co-Optimize
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Helpdesk\Tests\Unit;

use OCA\Helpdesk\Service\RequestMail;
use PHPUnit\Framework\TestCase;

class RequestMailTest extends TestCase {
	private const LABELS = [
		'name' => 'Navn', 'email' => 'E-mail', 'user' => 'Nextcloud-bruger',
		'organisation' => 'Fra', 'urgent' => 'Markeret som haster', 'files' => 'Filer',
	];

	public function testBodyNamesTheSenderAndEndsWithTheMarker(): void {
		$body = RequestMail::body("Printeren printer ikke.\n", self::LABELS, [
			'name' => 'Kristian Hansen', 'email' => 'kristian@firma.dk', 'uid' => 'kristian',
			'organisation' => 'Firma', 'server' => 'https://cloud.firma.dk', 'urgent' => true, 'files' => ['fejl.png'],
		]);
		$this->assertStringStartsWith("Printeren printer ikke.\n\n", $body);
		$this->assertStringContainsString('Navn: Kristian Hansen', $body);
		$this->assertStringContainsString('Fra: Firma (https://cloud.firma.dk)', $body);
		$this->assertStringContainsString('Markeret som haster', $body);
		$this->assertStringContainsString('Filer: fejl.png', $body);
		// Ticket system for Nextcloud looks for exactly this last line.
		$this->assertStringEndsWith("\n\nSent with Helpdesk for Nextcloud\n", $body);
	}

	public function testNotUrgentAndNoFilesLeaveThoseLinesOut(): void {
		$body = RequestMail::body('Hej', self::LABELS, [
			'name' => null, 'email' => 'a@b.dk', 'uid' => 'anna', 'organisation' => 'Firma',
			'server' => '', 'urgent' => false, 'files' => [],
		]);
		$this->assertStringContainsString('Navn: anna', $body);
		$this->assertStringNotContainsString('haster', $body);
		$this->assertStringNotContainsString('Filer', $body);
	}

	public function testRetryGetsSlower(): void {
		$this->assertSame(300, RequestMail::retryDelay(1));
		$this->assertSame(900, RequestMail::retryDelay(2));
		$this->assertSame(3600, RequestMail::retryDelay(3));
		$this->assertSame(10800, RequestMail::retryDelay(9));
	}

	public function testMessageIdsUseTheSendersDomain(): void {
		$id = RequestMail::newMessageId('noreply@firma.dk');
		$this->assertMatchesRegularExpression('/^helpdesk\.[0-9a-f]{24}@firma\.dk$/', $id);
		$this->assertNotSame($id, RequestMail::newMessageId('noreply@firma.dk'));
		$this->assertStringEndsWith('@localhost', RequestMail::newMessageId('broken'));
	}

	public function testThreadHeadersAnswerTheLatestAndReferAll(): void {
		$this->assertSame(['inReplyTo' => null, 'references' => []], RequestMail::threadHeaders([]));
		$this->assertSame(['inReplyTo' => 'b@x', 'references' => ['a@x', 'b@x']], RequestMail::threadHeaders(['a@x', '', 'b@x', 'a@x']));
	}

	public function testAFollowUpIsTheTextAndTheMarker(): void {
		$this->assertSame("Det virker stadig ikke\n\nSent with Helpdesk for Nextcloud\n", RequestMail::followUpBody("Det virker stadig ikke\n"));
	}

	public function testReplySubjectAddsReOnce(): void {
		$this->assertSame('Re: Printer', RequestMail::replySubject('Printer'));
		$this->assertSame('RE: Printer', RequestMail::replySubject('RE: Printer'));
	}

	public function testFilenamesLoseTheirPaths(): void {
		$this->assertSame('passwd', RequestMail::cleanFilename('../../etc/passwd'));
		$this->assertSame('skærm.png', RequestMail::cleanFilename('C:\\Users\\x\\skærm.png'));
		$this->assertSame('a_b.txt', RequestMail::cleanFilename("a\x01b.txt"));
		$this->assertSame('file', RequestMail::cleanFilename('...'));
	}
}
