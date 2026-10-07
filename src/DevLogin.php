<?php

namespace VolkmannDesignCode\DevLogin;

use Closure;
use Kirby\Cms\App;
use Kirby\Cms\User;
use Kirby\Cms\Users;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;

/**
 * One-click login for development: the Panel's login form gets a button
 * per account, which logs that account in without a password.
 *
 * Off unless `debug` is on and the site runs on a local host (Kirby's
 * `isLocal()`), or the `enabled` option says otherwise. While off, its
 * API routes answer 404 and the login form looks as always.
 *
 * @package   Kirby Dev Login
 * @author    Enzo Volkmann <enzo@volkmann.dev>
 * @link      https://github.com/volkmann-design-code/kirby-dev-login
 * @copyright volkmann design code
 * @license   MIT
 */
class DevLogin
{
	public const OPTION = 'volkmann-design-code.dev-login';

	public static function enabled(App $kirby): bool
	{
		$enabled = $kirby->option(static::OPTION . '.enabled');

		if ($enabled instanceof Closure) {
			$enabled = $enabled($kirby);
		}

		if (is_bool($enabled) === true) {
			return $enabled;
		}

		return $kirby->option('debug') === true && $kirby->environment()->isLocal() === true;
	}

	/**
	 * The accounts that get a button: the `users` option (a closure that
	 * returns users or email addresses) or every account, in both cases
	 * only those who may open the Panel; by role, then name.
	 */
	public static function users(App $kirby): Users
	{
		$users = $kirby->option(static::OPTION . '.users');
		$users = $users instanceof Closure ? $users($kirby) : $kirby->users();

		if (is_array($users) === true) {
			$users = $kirby->users()->filter('email', 'in', $users);
		}

		return $users
			->filter(fn (User $user) => $user->role()->permissions()->for('access', 'panel') === true)
			->sortBy('role', 'asc', 'name', 'asc', 'email', 'asc');
	}

	/**
	 * `GET dev-login`: the buttons; with the `description` option, each
	 * with its role's description (cards); `collapse`: whether Kirby's
	 * form waits behind a button
	 */
	public static function list(App $kirby): array
	{
		static::guard($kirby);

		$description = $kirby->option(static::OPTION . '.description') === true;

		return [
			'users' => static::users($kirby)->values(fn (User $user) => [
				'email' => $user->email(),
				'name'  => $user->name()->or($user->email())->value(),
				'role'  => $user->role()->title(),
				...($description === true ? ['description' => $user->role()->description() ?? ''] : []),
			]),
			'collapse' => $kirby->option(static::OPTION . '.collapse') === true,
		];
	}

	/**
	 * `POST dev-login` with `email`: logs that account in
	 */
	public static function login(App $kirby, string|null $email): array
	{
		static::guard($kirby);

		// like Kirby's own login route
		if ($kirby->auth()->type() === 'session' && $kirby->auth()->csrf() === false) {
			throw new InvalidArgumentException(message: 'Invalid CSRF token');
		}

		$user = static::users($kirby)->findBy('email', $email ?? '');

		// Kirby's own error, in the Panel's language
		if ($user === null) {
			throw new NotFoundException(key: 'user.notFound', data: ['name' => $email]);
		}

		$user->loginPasswordless();

		return ['status' => 'ok'];
	}

	protected static function guard(App $kirby): void
	{
		if (static::enabled($kirby) === false) {
			throw new NotFoundException();
		}
	}
}
