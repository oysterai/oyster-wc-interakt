<?php
/**
 * Bootstrap for the unit suite.
 *
 * WordPress is deliberately not loaded. The classes covered here are plain PHP
 * that happens to live in a plugin.
 *
 * Every WordPress function stubbed below is one whose real behaviour is trivial
 * enough that a stub cannot drift meaningfully from it. Anything with genuine
 * WordPress semantics (options, hooks, the queue, HTTP) is out of scope for this
 * suite by design, and covered by the integration suite instead.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

// The plugin files all guard on this; without it every include exits.
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
	/** WordPress returns the string unchanged when no translation is loaded. */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string { // phpcs:ignore
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );

		return $remove_breaks ? trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $text ) ) : $text;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/** A fixed value: the tests only need it to be stable within a run. */
	function wp_salt( string $scheme = 'auth' ): string { // phpcs:ignore
		return 'oyster-wc-interakt-test-salt-' . $scheme;
	}
}
