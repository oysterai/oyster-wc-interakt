<?php
/**
 * Self-update via GitHub Releases.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Support;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Not distributed through wordpress.org, so core has no way to learn a newer
 * version exists. Plugin Update Checker (vendored at lib/plugin-update-checker/,
 * MIT, no Composer) hooks the same core update machinery a wordpress.org-hosted
 * plugin would use, pointed at this repo's releases instead.
 */
final class Self_Updater {

	private const REPO_URL = 'https://github.com/oysterai/oyster-wc-interakt/';

	private const SLUG = 'oyster-wc-interakt';

	public static function register(): void {
		require_once OYSTER_WC_INTERAKT_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';

		$checker = PucFactory::buildUpdateChecker( self::REPO_URL, OYSTER_WC_INTERAKT_FILE, self::SLUG );

		// The zip built by bin/build-release-zip.sh and attached to each release,
		// not GitHub's auto-generated "Source code" archive, which would ship
		// tests and tooling to every merchant.
		$checker->getVcsApi()->enableReleaseAssets();
	}
}
