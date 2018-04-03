<?php
// phpcs:disable
/**
 * This is the bootstrap file for Integration Tests -- run with composer wp-tests
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if (! $_tests_dir) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested and plugins it depends on
 */
function _manually_load_plugin()
{
	require dirname(dirname(__FILE__)) . '/caldera-forms-query.php';
	require __DIR__ . '/plugins/caldera-forms/caldera-core.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
// phpcs:enable
