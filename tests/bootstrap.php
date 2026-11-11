<?php
// phpcs:disable PSR1.Files.SideEffects -- a bootstrap defines constants and loads files by design
/**
 *
 * Post Prune. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Uwe Flagmeyer
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * Standalone PHPUnit bootstrap: no phpBB kernel, only phpBB's class loader, constants and
 * its real sqlite3 DB driver (in-memory). Run inside the test board container:
 *   phpunit9 --no-configuration --bootstrap phpBB/ext/uflagmey/postprune/tests/bootstrap.php \
 *     --test-suffix _test.php phpBB/ext/uflagmey/postprune/tests/
 */
define('IN_PHPBB', true);

$phpbb_root_path = getenv('PHPBB_ROOT') ?: '/var/www/html/phpBB/';

require $phpbb_root_path . 'vendor/autoload.php';
require $phpbb_root_path . 'phpbb/class_loader.php';
require $phpbb_root_path . 'includes/constants.php';
require $phpbb_root_path . 'includes/utf/utf_tools.php';

(new \phpbb\class_loader('phpbb\\', $phpbb_root_path . 'phpbb/', 'php'))->register();
(new \phpbb\class_loader('uflagmey\\postprune\\', __DIR__ . '/../', 'php'))->register();

require __DIR__ . '/prune/fixtures.php';
