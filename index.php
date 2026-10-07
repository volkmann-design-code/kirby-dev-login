<?php

use Kirby\Filesystem\F;
use VolkmannDesignCode\DevLogin\DevLogin;

// for ZIP and submodule installs; Composer autoloads it
F::loadClasses([
	'VolkmannDesignCode\\DevLogin\\DevLogin' => 'src/DevLogin.php',
], __DIR__);

Kirby::plugin(
	name: 'volkmann-design-code/dev-login',
	extends: [
		'options' => [
			// null: on with `debug` on a local host; or true, false, a closure
			'enabled'     => null,
			// null: every account; or a closure returning users or emails
			'users'       => null,
			// true: a card per account with its role's description
			'description' => false,
			// true: the accounts first, Kirby's login form behind a button
			'collapse'    => false,
		],
		'api' => [
			'routes' => [
				[
					'pattern' => 'dev-login',
					'method'  => 'GET',
					'auth'    => false,
					'action'  => function () {
						return DevLogin::list($this->kirby());
					},
				],
				[
					'pattern' => 'dev-login',
					'method'  => 'POST',
					'auth'    => false,
					'action'  => function () {
						return DevLogin::login($this->kirby(), $this->requestBody('email'));
					},
				],
			],
		],
		'translations' => [
			'en' => [
				'volkmann-design-code.dev-login.label' => 'Log in as',
				'volkmann-design-code.dev-login.form'  => 'Log in with email',
			],
			'de' => [
				'volkmann-design-code.dev-login.label' => 'Anmelden als',
				'volkmann-design-code.dev-login.form'  => 'Mit E-Mail anmelden',
			],
		],
	]
);
