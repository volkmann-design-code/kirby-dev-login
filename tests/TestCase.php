<?php

namespace VolkmannDesignCode\DevLogin\Tests;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\Str;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * A plain Kirby site with three roles, one of them without Panel access.
 * Each test has its own temp root; `app()` builds Kirby for one request.
 */
abstract class TestCase extends BaseTestCase
{
	public const ACCOUNTS = [
		['email' => 'admin@example.com', 'name' => 'Ada', 'role' => 'admin'],
		['email' => 'zoe@example.com', 'name' => 'Zoe', 'role' => 'editor'],
		['email' => 'bob@example.com', 'name' => 'Bob', 'role' => 'editor'],
		['email' => 'client@example.com', 'name' => 'Client', 'role' => 'client'],
	];

	public const LOCAL = ['SERVER_NAME' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1'];

	protected string $root;
	protected array $apps = [];

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/kirby-dev-login-test-' . Str::random(8, 'alphaNum');
		Dir::make($this->root . '/content');

		$kirby = $this->app();
		$kirby->impersonate('kirby');

		// Kirby wants a password only for the first account; hashing one
		// takes a while
		foreach (static::ACCOUNTS as $i => $account) {
			$kirby->users()->create($i === 0 ? [...$account, 'password' => 'test-password'] : $account);
		}
	}

	protected function tearDown(): void
	{
		// written at shutdown otherwise, after the test's folder is gone;
		// newest first: a login's app holds its session's lock, and the
		// others would wait for it through the cookie the login set
		foreach (array_reverse($this->apps) as $app) {
			$app->session()->destroy();
		}

		// a login sets Kirby's session cookie for the whole process
		$_COOKIE = [];

		Dir::remove($this->root);
	}

	/**
	 * Kirby for one request: debug on, from localhost unless `server`
	 * says otherwise, with a valid CSRF token unless `request` overrides it
	 */
	protected function app(array $options = [], array $server = self::LOCAL, array $request = []): App
	{
		return $this->apps[] = new App([
			'roots' => [
				'index'    => $this->root,
				'content'  => $this->root . '/content',
				'site'     => $this->root . '/site',
				'media'    => $this->root . '/media',
				'sessions' => $this->root . '/sessions',
			],
			'blueprints' => [
				'users/editor' => ['name' => 'editor', 'title' => 'Editor', 'description' => 'Edits pages'],
				'users/client' => ['name' => 'client', 'title' => 'Client', 'permissions' => ['access' => ['panel' => false]]],
			],
			'options' => [
				'debug'    => true,
				'api.csrf' => 'test-token',
				...$options,
			],
			'server'  => $server,
			'request' => ['query' => ['csrf' => 'test-token'], ...$request],
		]);
	}
}
