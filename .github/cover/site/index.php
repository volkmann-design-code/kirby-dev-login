<?php

/**
 * The Kirby site for the cover image (`../cover.mjs`): made-up accounts,
 * English Panel, this plugin. Kirby is the repository's dev dependency
 * (`kirby/`); content, accounts and sessions live in `COVER_DATA`, a temp
 * folder, so every run starts fresh. `php index.php seed` creates the
 * accounts.
 */

$repo = dirname(__DIR__, 3);
$data = getenv('COVER_DATA') ?: exit("COVER_DATA is not set\n");

require $repo . '/vendor/autoload.php';

$kirby = new Kirby([
	'roots' => [
		'index'      => __DIR__,
		'kirby'      => $repo . '/kirby',
		'blueprints' => __DIR__ . '/blueprints',
		'plugins'    => $data . '/plugins',
		'content'    => $data . '/content',
		'accounts'   => $data . '/accounts',
		'sessions'   => $data . '/sessions',
		'cache'      => $data . '/cache',
	],
	'options' => [
		'debug' => true,
		'panel' => ['language' => 'en'],
	],
]);

if (PHP_SAPI === 'cli' && ($argv[1] ?? null) === 'seed') {
	$kirby->impersonate('kirby');

	// Kirby wants a password for the first account only
	foreach ([
		['email' => 'mara@example.com', 'name' => 'Mara Lind', 'role' => 'admin', 'password' => 'cover-password'],
		['email' => 'tom@example.com', 'name' => 'Tom Ruiz', 'role' => 'client'],
		['email' => 'jonas@example.com', 'name' => 'Jonas Weber', 'role' => 'editor'],
		['email' => 'priya@example.com', 'name' => 'Priya Nair', 'role' => 'editor'],
	] as $account) {
		$kirby->users()->create($account);
	}

	return;
}

echo $kirby->render();
