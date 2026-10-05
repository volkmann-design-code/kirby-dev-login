<?php

namespace VolkmannDesignCode\DevLogin\Tests;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\TestWith;
use VolkmannDesignCode\DevLogin\DevLogin;

class DevLoginTest extends TestCase
{
	protected function list(App $kirby): array
	{
		return $kirby->api()->call('dev-login', 'GET');
	}

	protected function login(App $kirby, string $email): array
	{
		return $kirby->api()->call('dev-login', 'POST', ['body' => ['email' => $email]]);
	}

	public function testItIsOnWithDebugOnALocalHost(): void
	{
		$this->assertTrue(DevLogin::enabled($this->app()));
		$this->assertFalse(DevLogin::enabled($this->app(['debug' => false])));
		$this->assertFalse(DevLogin::enabled($this->app([], ['SERVER_NAME' => 'example.com', 'REMOTE_ADDR' => '203.0.113.7'])));
		// a local host name behind a proxy that forwards a visitor
		$this->assertFalse(DevLogin::enabled($this->app([], [...self::LOCAL, 'SERVER_NAME' => 'example.com', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'])));
	}

	public function testTheOptionDecidesWhenSet(): void
	{
		$this->assertFalse(DevLogin::enabled($this->app([DevLogin::OPTION . '.enabled' => false])));
		$this->assertTrue(DevLogin::enabled($this->app(['debug' => false, DevLogin::OPTION . '.enabled' => true])));
		$this->assertTrue(DevLogin::enabled($this->app(['debug' => false, DevLogin::OPTION . '.enabled' => fn (App $kirby) => $kirby instanceof App])));
	}

	public function testItListsTheAccountsWithPanelAccessByRoleThenName(): void
	{
		$this->assertSame([
			['email' => 'admin@example.com', 'name' => 'Ada', 'role' => 'Admin'],
			['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'Editor'],
			['email' => 'zoe@example.com', 'name' => 'Zoe', 'role' => 'Editor'],
		], $this->list($this->app())['users']);
	}

	public function testTheUsersOptionNarrowsTheList(): void
	{
		$kirby = $this->app([DevLogin::OPTION . '.users' => fn () => ['zoe@example.com', 'client@example.com', 'nobody@example.com']]);
		$this->assertSame(['zoe@example.com'], array_column($this->list($kirby)['users'], 'email'));

		$kirby = $this->app([DevLogin::OPTION . '.users' => fn (App $kirby) => $kirby->users()->role('admin')]);
		$this->assertSame(['admin@example.com'], array_column($this->list($kirby)['users'], 'email'));
	}

	public function testALoginStartsASessionWithoutAPassword(): void
	{
		$kirby = $this->app();

		$this->assertSame(['status' => 'ok'], $this->login($kirby, 'zoe@example.com'));
		$this->assertSame('zoe@example.com', $kirby->user()?->email());
		$this->assertSame($kirby->user()->id(), $kirby->session()->get('kirby.userId'));
	}

	#[TestWith(['client@example.com'])]
	#[TestWith(['nobody@example.com'])]
	public function testOnlyListedAccountsCanBeLoggedIn(string $email): void
	{
		$kirby = $this->app();

		try {
			$this->login($kirby, $email);
			$this->fail($email);
		} catch (NotFoundException $e) {
			// Kirby's own error, translated in the Panel
			$this->assertSame('error.user.notFound', $e->getKey());
			$this->assertNull($kirby->user());
		}
	}

	public function testALoginNeedsTheCsrfToken(): void
	{
		$kirby = $this->app([], self::LOCAL, ['query' => ['csrf' => 'wrong']]);

		$this->expectException(InvalidArgumentException::class);
		$this->login($kirby, 'zoe@example.com');
	}

	public function testWhileOffBothRoutesAnswerNotFound(): void
	{
		$kirby = $this->app(['debug' => false]);

		foreach ([fn () => $this->list($kirby), fn () => $this->login($kirby, 'zoe@example.com')] as $call) {
			try {
				$call();
				$this->fail('answered while off');
			} catch (NotFoundException) {
				$this->assertNull($kirby->user());
			}
		}
	}
}
