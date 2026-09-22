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
			__( 'Oyster WhatsApp', 'oyster-wc-interakt' ),
			__( 'Oyster WhatsApp', 'oyster-wc-interakt' ),
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

		// Unslashed but not otherwise filtered here: Settings::save() sanitises each
		// field, and the two API keys must survive verbatim.
		Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		wp_safe_redirect( add_query_arg( 'updated', '1', menu_page_url( self::SLUG, false ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Oyster WhatsApp', 'oyster-wc-interakt' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'oyster-wc-interakt' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="oyster_wc_interakt_save" />
				<?php wp_nonce_field( self::NONCE ); ?>

				<h2><?php esc_html_e( 'Keys', 'oyster-wc-interakt' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="oyster_api_key"><?php esc_html_e( 'Oyster API key', 'oyster-wc-interakt' ); ?></label></th>
						<td>
							<input name="oyster_api_key" id="oyster_api_key" type="password" class="regular-text" autocomplete="off" value="" />
							<p class="description">
								<?php esc_html_e( 'Create one in your Oyster dashboard with the "Deliver scan results" scope. Leave blank to keep the current key.', 'oyster-wc-interakt' ); ?>
								<?php echo Settings::oyster_api_key() ? '<strong>' . esc_html__( 'A key is stored.', 'oyster-wc-interakt' ) . '</strong>' : ''; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="interakt_api_key"><?php esc_html_e( 'Interakt API key', 'oyster-wc-interakt' ); ?></label></th>
						<td>
							<input name="interakt_api_key" id="interakt_api_key" type="password" class="regular-text" autocomplete="off" value="" />
							<p class="description">
								<?php esc_html_e( 'From Interakt, under Developer Settings. Leave blank to keep the current key.', 'oyster-wc-interakt' ); ?>
								<?php echo Settings::interakt_api_key() ? '<strong>' . esc_html__( 'A key is stored.', 'oyster-wc-interakt' ) . '</strong>' : ''; ?>
							</p>
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

				<h2><?php esc_html_e( 'Writing your template', 'oyster-wc-interakt' ); ?></h2>
				<p><?php esc_html_e( 'Leave a template name blank to record the event only, and build the message yourself in Interakt. Name a template and this plugin sends it directly, which is the only way to attach the PDF report.', 'oyster-wc-interakt' ); ?></p>
				<p><?php esc_html_e( 'A named template is sent with the report PDF as its document header, and these body variables in order:', 'oyster-wc-interakt' ); ?></p>
				<ol>
					<li><code>{{1}}</code> &mdash; <?php esc_html_e( "the shopper's first name", 'oyster-wc-interakt' ); ?></li>
					<li><code>{{2}}</code> &mdash; <?php esc_html_e( 'the one-line summary of their scan', 'oyster-wc-interakt' ); ?></li>
					<li><code>{{3}}</code> &mdash; <?php esc_html_e( 'their recommended products, comma separated', 'oyster-wc-interakt' ); ?></li>
					<li><code>{{4}}</code> &mdash; <?php esc_html_e( 'a link that adds those products to their cart', 'oyster-wc-interakt' ); ?></li>
				</ol>

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
			<tr>
				<th scope="row"><label for="template_<?php echo esc_attr( $event ); ?>"><?php esc_html_e( 'Template name', 'oyster-wc-interakt' ); ?></label></th>
				<td>
					<input name="template_<?php echo esc_attr( $event ); ?>" id="template_<?php echo esc_attr( $event ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( Settings::template_name( $event ) ); ?>" />
					<input name="template_language_<?php echo esc_attr( $event ); ?>" type="text" class="small-text" value="<?php echo esc_attr( Settings::template_language( $event ) ); ?>" aria-label="<?php esc_attr_e( 'Template language code', 'oyster-wc-interakt' ); ?>" />
					<p class="description"><?php esc_html_e( 'The template code name from Interakt, and its language code. Blank sends no message.', 'oyster-wc-interakt' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
