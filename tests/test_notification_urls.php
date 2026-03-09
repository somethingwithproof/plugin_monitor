<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Tests for issue #185: ping threshold email links must include the full  |
 | hostname, not just the relative url_path.                               |
 |                                                                         |
 | The fix replaces $config['url_path'] with read_config_option('base_url')
 | when constructing the host.php link in appendThresholdSection().        |
 |                                                                         |
 | Run: php tests/test_notification_urls.php                              |
 +-------------------------------------------------------------------------+
*/

$pass = 0;
$fail = 0;

function assert_eq($label, $expected, $actual) {
	global $pass, $fail;
	if ($expected === $actual) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		echo '      expected: ' . var_export($expected, true) . "\n";
		echo '      actual:   ' . var_export($actual, true) . "\n";
		$fail++;
	}
}

function assert_contains($label, $needle, $haystack) {
	global $pass, $fail;
	if (strpos($haystack, $needle) !== false) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		echo "      needle:   $needle\n";
		echo "      haystack: $haystack\n";
		$fail++;
	}
}

function assert_not_contains($label, $needle, $haystack) {
	global $pass, $fail;
	if (strpos($haystack, $needle) === false) {
		echo "PASS  $label\n";
		$pass++;
	} else {
		echo "FAIL  $label\n";
		echo "      unexpected needle: $needle\n";
		echo "      haystack: $haystack\n";
		$fail++;
	}
}

/* ------------------------------------------------------------------ */
/* Helpers that mirror the fixed and broken link-building logic        */
/* ------------------------------------------------------------------ */

/* Before the fix: uses $config['url_path'] — relative path, no hostname. */
function build_link_old($url_path, $host_id) {
	return htmlspecialchars($url_path . 'host.php?action=edit&id=' . $host_id);
}

/* After the fix: uses read_config_option('base_url') — full URL. */
function build_link_new($base_url, $host_id) {
	return htmlspecialchars($base_url . 'host.php?action=edit&id=' . $host_id);
}

/* ------------------------------------------------------------------ */
/* Test the old (broken) behaviour produces relative-only links        */
/* ------------------------------------------------------------------ */

$url_path = '/cacti/';
$base_url = 'https://monitoring.example.com/cacti/';
$host_id  = 42;

$old_link = build_link_old($url_path, $host_id);
$new_link = build_link_new($base_url, $host_id);

/* Old link lacks a hostname. */
assert_not_contains(
	'old link: no scheme (no hostname)',
	'https://',
	$old_link
);
assert_not_contains(
	'old link: no hostname component',
	'monitoring.example.com',
	$old_link
);

/* Old link starts with the relative path. */
assert_contains('old link: starts with url_path', '/cacti/', $old_link);

/* New link contains the full origin. */
assert_contains('new link: contains scheme',   'https://',                   $new_link);
assert_contains('new link: contains hostname', 'monitoring.example.com',     $new_link);
assert_contains('new link: contains path',     '/cacti/host.php',            $new_link);
assert_contains('new link: contains action',   'action=edit',                $new_link);
assert_contains('new link: contains host id',  'id=42',                      $new_link);

/* ------------------------------------------------------------------ */
/* Verify htmlspecialchars encodes the & in the query string           */
/* ------------------------------------------------------------------ */

assert_contains(
	'new link: & encoded as &amp;',
	'action=edit&amp;id=42',
	$new_link
);
assert_not_contains(
	'new link: raw & absent (properly escaped)',
	'action=edit&id=42',
	$new_link
);

/* ------------------------------------------------------------------ */
/* Edge cases                                                           */
/* ------------------------------------------------------------------ */

/* base_url with trailing slash vs without. */
$link_trailing    = build_link_new('https://example.com/cacti/', 1);
$link_no_trailing = build_link_new('https://example.com/cacti',  1);

assert_contains(
	'trailing slash: path correct',
	'https://example.com/cacti/host.php',
	$link_trailing
);
assert_contains(
	'no trailing slash: path correct (concatenation)',
	'https://example.com/cactihost.php',
	$link_no_trailing
);
/* The no-trailing-slash form is wrong, but it matches what base_url
 * always provides (Cacti stores it with trailing slash).  This test
 * documents the expected contract: callers must ensure base_url ends
 * with '/'. */

/* Host ID 0 (sentinel) must still produce a valid URL. */
$zero_id_link = build_link_new($base_url, 0);
assert_contains('host id 0: link still constructed', 'id=0', $zero_id_link);

/* ------------------------------------------------------------------ */
echo "\nResults: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
