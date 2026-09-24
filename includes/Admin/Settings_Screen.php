<?php
/**
 * Settings screen.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Admin;

use Oyster\WcInterakt\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings_Screen {

	private const SLUG = 'oyster-wc-interakt';

	private const NONCE = 'oyster_wc_interakt_save';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_oyster_wc_interakt_save', array( $this, 'handle_save' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			'options-general.php',
			__( 'Oyster Interakt', 'oyster-wc-interakt' ),
			__( 'Oyster Interakt', 'oyster-wc-interakt' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'oyster-wc-interakt' ) );
		}

		check_admin_referer( self::NONCE );

		// Unslashed but not otherwise filtered: Settings::save() sanitises each field,
		// and the API key must survive verbatim.
		Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Built by hand rather than with menu_page_url(): admin-post.php fires admin_init
		// but never loads the admin menu, so that returns an empty string here and the
		// redirect lands nowhere.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Oyster Interakt', 'oyster-wc-interakt' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'oyster-wc-interakt' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oyster_wc_interakt_save" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<h2><?php esc_html_e( 'Interakt', 'oyster-wc-interakt' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $has_key = null !== Settings::interakt_api_key(); ?>
					<tr>
						<th scope="row"><label for="interakt_api_key"><?php esc_html_e( 'Interakt API key', 'oyster-wc-interakt' ); ?></label></th>
						<td>
							<?php
							/*
							 * Masked in the placeholder, never the value. A value would be posted
							 * back untouched on the next save and stored as the key, destroying
							 * the real one, since only a blank field means "keep what you have".
							 */
							?>
							<input
								name="interakt_api_key"
								id="interakt_api_key"
								type="password"
								class="regular-text"
								autocomplete="off"
								value=""
								placeholder="<?php echo esc_attr( $has_key ? str_repeat( '•', 28 ) : '' ); ?>" />
							<?php if ( $has_key ) : ?>
								<p class="description" style="color:#00a32a;">
									<span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;"></span>
									<?php esc_html_e( 'A key is saved. Leave this blank to keep it, or paste a new one to replace it.', 'oyster-wc-interakt' ); ?>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'From Interakt, under Developer Settings.', 'oyster-wc-interakt' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php
				$this->render_event(
					Settings::EVENT_SCAN,
					__( 'When a scan finishes', 'oyster-wc-interakt' )
				);
				$this->render_event(
					Settings::EVENT_RECOMMENDATION,
					__( 'When recommendations are ready', 'oyster-wc-interakt' )
				);
				?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_event( string $event, string $title ): void {
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Send to Interakt', 'oyster-wc-interakt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enable_<?php echo esc_attr( $event ); ?>" value="1" <?php checked( Settings::event_enabled( $event ) ); ?> />
						<?php esc_html_e( 'Enabled', 'oyster-wc-interakt' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="event_name_<?php echo esc_attr( $event ); ?>"><?php esc_html_e( 'Interakt event name', 'oyster-wc-interakt' ); ?></label></th>
				<td>
					<input name="event_name_<?php echo esc_attr( $event ); ?>" id="event_name_<?php echo esc_attr( $event ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( Settings::event_name( $event ) ); ?>" />
					<p class="description"><?php esc_html_e( 'What this event is called on the customer timeline in Interakt.', 'oyster-wc-interakt' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
