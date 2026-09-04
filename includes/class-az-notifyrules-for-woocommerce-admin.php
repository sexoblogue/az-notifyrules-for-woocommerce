<?php

defined( 'ABSPATH' ) || exit;

final class AZ_Woo_Alerts_Admin {
	private AZ_Woo_Alerts $plugin;

	public function __construct( AZ_Woo_Alerts $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_az_woo_alerts_save_channels', array( $this, 'save_channels' ) );
		add_action( 'admin_post_az_woo_alerts_save_rules', array( $this, 'save_rules' ) );
		add_action( 'admin_post_az_woo_alerts_test_channel', array( $this, 'test_channel' ) );
		add_action( 'wp_ajax_az_woo_alerts_reveal_secret', array( $this, 'reveal_secret' ) );
		add_action( 'wp_ajax_az_woo_alerts_edit_channel', array( $this, 'edit_channel' ) );
		add_action( 'wp_ajax_az_woo_alerts_save_channel', array( $this, 'save_channel' ) );
		add_action( 'wp_ajax_az_woo_alerts_delete_channel', array( $this, 'delete_channel' ) );
		add_action( 'wp_ajax_az_woo_alerts_delete_rule', array( $this, 'delete_rule' ) );
		add_action( 'wp_ajax_az_woo_alerts_save_rule', array( $this, 'save_rule' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Order alerts', 'az-notifyrules-for-woocommerce' ),
			__( 'Order alerts', 'az-notifyrules-for-woocommerce' ),
			'manage_woocommerce',
			'az-notifyrules-for-woocommerce',
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( 'woocommerce_page_az-notifyrules-for-woocommerce' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'az-notifyrules-for-woocommerce-admin', AZ_WOO_ALERTS_URL . 'assets/admin.css', array(), AZ_WOO_ALERTS_VERSION );
		wp_enqueue_script( 'az-notifyrules-for-woocommerce-admin', AZ_WOO_ALERTS_URL . 'assets/admin.js', array( 'jquery', 'wc-enhanced-select' ), AZ_WOO_ALERTS_VERSION, true );
		wp_localize_script(
			'az-notifyrules-for-woocommerce-admin',
			'AZWooAlertsAdmin',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'revealNonce'        => wp_create_nonce( 'az_woo_alerts_reveal_secret' ),
				'editChannelNonce'   => wp_create_nonce( 'az_woo_alerts_edit_channel' ),
				'saveChannelNonce'   => wp_create_nonce( 'az_woo_alerts_save_channel' ),
				'deleteChannelNonce' => wp_create_nonce( 'az_woo_alerts_delete_channel' ),
				'deleteRuleNonce'    => wp_create_nonce( 'az_woo_alerts_delete_rule' ),
				'saveRuleNonce'      => wp_create_nonce( 'az_woo_alerts_save_rule' ),
				'revealLabel'        => __( 'Show', 'az-notifyrules-for-woocommerce' ),
				'hideLabel'          => __( 'Hide', 'az-notifyrules-for-woocommerce' ),
				'revealError'        => __( 'Unable to reveal this credential.', 'az-notifyrules-for-woocommerce' ),
				'loadingLabel'       => __( 'Loading…', 'az-notifyrules-for-woocommerce' ),
				'editChannelError'   => __( 'Unable to load this connection.', 'az-notifyrules-for-woocommerce' ),
				/* translators: %s: connection name. */
				'deleteChannelConfirm' => __( 'Permanently delete the “%s” connection? Deletion is immediate.', 'az-notifyrules-for-woocommerce' ),
				'deletingChannelLabel' => __( 'Deleting…', 'az-notifyrules-for-woocommerce' ),
				'deleteChannelError' => __( 'Unable to delete this connection.', 'az-notifyrules-for-woocommerce' ),
				'savingChannelLabel' => __( 'Saving…', 'az-notifyrules-for-woocommerce' ),
				'saveChannelError'   => __( 'Unable to save this connection.', 'az-notifyrules-for-woocommerce' ),
				/* translators: %s: rule name. */
				'deleteRuleConfirm'  => __( 'Permanently delete the “%s” rule? Deletion is immediate.', 'az-notifyrules-for-woocommerce' ),
				'deletingRuleLabel'  => __( 'Deleting…', 'az-notifyrules-for-woocommerce' ),
				'deleteRuleError'    => __( 'Unable to delete this rule.', 'az-notifyrules-for-woocommerce' ),
				'savingRuleLabel'    => __( 'Saving…', 'az-notifyrules-for-woocommerce' ),
				'saveRuleError'      => __( 'Unable to save this rule.', 'az-notifyrules-for-woocommerce' ),
				'storedMask'         => AZ_Woo_Alerts_Crypto::STORED_MASK,
			)
		);
	}

	public function render(): void {
		$this->authorize();
		$requested_tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR );
		$tab           = is_string( $requested_tab ) ? sanitize_key( $requested_tab ) : 'rules';
		$tab = in_array( $tab, array( 'channels', 'rules' ), true ) ? $tab : 'rules';
		?>
		<div class="wrap az-notifyrules-for-woocommerce-wrap">
			<h1><?php esc_html_e( 'New order alerts', 'az-notifyrules-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Configure notification connections, then associate them with rules for all orders, product categories, or individual products.', 'az-notifyrules-for-woocommerce' ); ?></p>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'rules' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=az-notifyrules-for-woocommerce&tab=rules' ) ); ?>"><?php esc_html_e( 'Rules', 'az-notifyrules-for-woocommerce' ); ?></a>
				<a class="nav-tab <?php echo 'channels' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=az-notifyrules-for-woocommerce&tab=channels' ) ); ?>"><?php esc_html_e( 'Connections', 'az-notifyrules-for-woocommerce' ); ?></a>
			</nav>
			<?php $this->render_notices(); ?>
			<?php 'rules' === $tab ? $this->render_rules() : $this->render_channels(); ?>
		</div>
		<?php
	}

	public function save_channels(): void {
		$this->authorize();
		check_admin_referer( 'az_woo_alerts_save_channels' );

		// Each value is sanitized according to its expected type by the provider below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_channels = isset( $_POST['channels'] ) && is_array( $_POST['channels'] ) ? wp_unslash( $_POST['channels'] ) : array();
		$existing     = $this->plugin->channels_by_id();
		$providers    = $this->plugin->providers();
		$channels     = array();
		$errors       = array();

		foreach ( $raw_channels as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$type     = sanitize_key( $raw['type'] ?? '' );
			$provider = $providers[ $type ] ?? null;
			if ( ! $provider ) {
				$errors[] = __( 'An unknown connection type was ignored.', 'az-notifyrules-for-woocommerce' );
				continue;
			}

			$id      = sanitize_key( $raw['id'] ?? '' );
			$result  = $provider->sanitize( $raw, $existing[ $id ] ?? array() );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				continue;
			}
			$channels[] = $result;
		}

		if ( $errors ) {
			$this->set_notice( 'error', implode( ' ', array_unique( $errors ) ) );
		} else {
			update_option( AZ_Woo_Alerts::OPTION_CHANNELS, $channels, false );
			$this->set_notice( 'success', __( 'The connections have been saved.', 'az-notifyrules-for-woocommerce' ) );
		}

		$this->redirect( 'channels' );
	}

	public function edit_channel(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_edit_channel', 'nonce' );

		$channel_id = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$index      = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : 0;
		$channel    = $this->plugin->channels_by_id()[ $channel_id ] ?? null;
		if ( ! is_array( $channel ) ) {
			wp_send_json_error( array( 'message' => __( 'This connection no longer exists.', 'az-notifyrules-for-woocommerce' ) ), 404 );
		}

		ob_start();
		$this->render_channel_edit_row( (string) $index, $channel );
		$html = ob_get_clean();

		nocache_headers();
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function save_channel(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_save_channel', 'nonce' );

		// Each value is sanitized according to its expected type by the provider below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['channel'] ) && is_array( $_POST['channel'] ) ? wp_unslash( $_POST['channel'] ) : array();
		if ( ! $raw ) {
			wp_send_json_error( array( 'message' => __( 'Connection data is missing.', 'az-notifyrules-for-woocommerce' ) ), 400 );
		}

		$channels       = $this->plugin->channels();
		$channels_by_id = $this->plugin->channels_by_id();
		$requested_id   = sanitize_key( $raw['id'] ?? '' );
		$existing       = $requested_id ? ( $channels_by_id[ $requested_id ] ?? null ) : null;
		$existing_index = null;
		if ( $requested_id && ! is_array( $existing ) ) {
			wp_send_json_error( array( 'message' => __( 'This connection no longer exists.', 'az-notifyrules-for-woocommerce' ) ), 404 );
		}
		if ( is_array( $existing ) ) {
			foreach ( $channels as $index => $stored_channel ) {
				if ( $requested_id === (string) ( $stored_channel['id'] ?? '' ) ) {
					$existing_index = $index;
					break;
				}
			}
		}

		$type     = sanitize_key( $raw['type'] ?? '' );
		$provider = $this->plugin->providers()[ $type ] ?? null;
		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown connection type.', 'az-notifyrules-for-woocommerce' ) ), 422 );
		}

		$channel = $provider->sanitize( $raw, is_array( $existing ) ? $existing : array() );
		if ( is_wp_error( $channel ) ) {
			wp_send_json_error( array( 'message' => $channel->get_error_message() ), 422 );
		}

		if ( null === $existing_index ) {
			$channels[] = $channel;
		} else {
			$channels[ $existing_index ] = $channel;
		}
		$channels = array_values( $channels );

		$updated = update_option( AZ_Woo_Alerts::OPTION_CHANNELS, $channels, false );
		if ( ! $updated && $channels !== get_option( AZ_Woo_Alerts::OPTION_CHANNELS, array() ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress could not save the connection.', 'az-notifyrules-for-woocommerce' ) ), 500 );
		}

		ob_start();
		$this->render_channel_display_row( (string) $channel['id'], $channel );
		$html = ob_get_clean();

		nocache_headers();
		wp_send_json_success(
			array(
				'id'      => (string) $channel['id'],
				'html'    => $html,
				'message' => __( 'The connection has been saved.', 'az-notifyrules-for-woocommerce' ),
			)
		);
	}

	public function delete_channel(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_delete_channel', 'nonce' );

		$channel_id = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		if ( '' === $channel_id ) {
			wp_send_json_error( array( 'message' => __( 'Connection identifier is missing.', 'az-notifyrules-for-woocommerce' ) ), 400 );
		}

		foreach ( $this->plugin->rules() as $rule ) {
			if ( in_array( $channel_id, (array) ( $rule['channel_ids'] ?? array() ), true ) ) {
				wp_send_json_error(
					array( 'message' => __( 'This connection is used by a rule. Edit that rule first.', 'az-notifyrules-for-woocommerce' ) ),
					409
				);
			}
		}

		$channels = $this->plugin->channels();
		$kept     = array();
		$deleted  = null;
		foreach ( $channels as $channel ) {
			if ( $channel_id === (string) ( $channel['id'] ?? '' ) ) {
				$deleted = $channel;
				continue;
			}
			$kept[] = $channel;
		}

		if ( null === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'This connection no longer exists.', 'az-notifyrules-for-woocommerce' ) ), 404 );
		}

		if ( ! update_option( AZ_Woo_Alerts::OPTION_CHANNELS, $kept, false ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress could not save the deletion.', 'az-notifyrules-for-woocommerce' ) ), 500 );
		}

		nocache_headers();
		wp_send_json_success(
			array(
				'id'      => $channel_id,
				'message' => __( 'The connection has been deleted.', 'az-notifyrules-for-woocommerce' ),
			)
		);
	}

	public function save_rules(): void {
		$this->authorize();
		check_admin_referer( 'az_woo_alerts_save_rules' );

		// Rule fields are sanitized according to their expected types by sanitize_rule().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_rules    = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : array();
		$channel_ids  = array_keys( $this->plugin->channels_by_id() );
		$rules        = array();
		$errors       = array();

		foreach ( $raw_rules as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$rule = $this->sanitize_rule( $raw, $channel_ids );
			if ( is_wp_error( $rule ) ) {
				$errors[] = $rule->get_error_message();
				continue;
			}
			$rules[] = $rule;
		}

		if ( $errors ) {
			$this->set_notice( 'error', implode( ' ', array_unique( $errors ) ) );
		} else {
			update_option( AZ_Woo_Alerts::OPTION_RULES, $rules, false );
			$this->set_notice( 'success', __( 'The rules have been saved.', 'az-notifyrules-for-woocommerce' ) );
		}

		$this->redirect( 'rules' );
	}

	public function test_channel(): void {
		$this->authorize();
		$channel_id = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
		check_admin_referer( 'az_woo_alerts_test_' . $channel_id );

		$channel  = $this->plugin->channels_by_id()[ $channel_id ] ?? null;
		$provider = $channel ? ( $this->plugin->providers()[ $channel['type'] ] ?? null ) : null;
		$orders   = wc_get_orders(
			array(
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		if ( ! $channel || ! $provider ) {
			$this->set_notice( 'error', __( 'This connection no longer exists.', 'az-notifyrules-for-woocommerce' ) );
		} elseif ( empty( $orders[0] ) || ! $orders[0] instanceof WC_Order ) {
			$this->set_notice( 'error', __( 'No reference order is available for the test.', 'az-notifyrules-for-woocommerce' ) );
		} else {
			$result = $provider->dispatch( $orders[0], $channel, true );
			$this->set_notice(
				true === $result ? 'success' : 'error',
				true === $result
					? __( 'The test request was accepted. Check the destination now.', 'az-notifyrules-for-woocommerce' )
					: ( is_wp_error( $result ) ? $result->get_error_message() : __( 'The test failed.', 'az-notifyrules-for-woocommerce' ) )
			);
		}

		$this->redirect( 'channels' );
	}

	public function reveal_secret(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_reveal_secret', 'nonce' );

		$channel_id = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		if ( ! in_array( $field, array( 'app_token', 'user_key' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown credential field.', 'az-notifyrules-for-woocommerce' ) ), 400 );
		}

		$channel = $this->plugin->channels_by_id()[ $channel_id ] ?? null;
		if ( ! is_array( $channel ) || 'pushover' !== ( $channel['type'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Pushover connection not found.', 'az-notifyrules-for-woocommerce' ) ), 404 );
		}

		$secret = AZ_Woo_Alerts_Crypto::decrypt( (string) ( $channel[ $field ] ?? '' ) );
		if ( '' === $secret ) {
			wp_send_json_error( array( 'message' => __( 'This credential is not configured or cannot be decrypted.', 'az-notifyrules-for-woocommerce' ) ), 409 );
		}

		nocache_headers();
		wp_send_json_success( array( 'value' => $secret ) );
	}

	public function delete_rule(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_delete_rule', 'nonce' );

		$rule_id = isset( $_POST['rule'] ) ? sanitize_key( wp_unslash( $_POST['rule'] ) ) : '';
		if ( '' === $rule_id ) {
			wp_send_json_error( array( 'message' => __( 'Rule identifier is missing.', 'az-notifyrules-for-woocommerce' ) ), 400 );
		}

		$rules   = $this->plugin->rules();
		$kept    = array();
		$deleted = null;
		foreach ( $rules as $rule ) {
			if ( $rule_id === (string) ( $rule['id'] ?? '' ) ) {
				$deleted = $rule;
				continue;
			}
			$kept[] = $rule;
		}

		if ( null === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'This rule no longer exists.', 'az-notifyrules-for-woocommerce' ) ), 404 );
		}

		if ( ! update_option( AZ_Woo_Alerts::OPTION_RULES, $kept, false ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress could not save the deletion.', 'az-notifyrules-for-woocommerce' ) ), 500 );
		}

		nocache_headers();
		wp_send_json_success(
			array(
				'id'      => $rule_id,
				'message' => __( 'The rule has been deleted.', 'az-notifyrules-for-woocommerce' ),
			)
		);
	}

	public function save_rule(): void {
		$this->authorize();
		check_ajax_referer( 'az_woo_alerts_save_rule', 'nonce' );

		// Rule fields are sanitized according to their expected types by sanitize_rule().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
		if ( ! $raw ) {
			wp_send_json_error( array( 'message' => __( 'Rule data is missing.', 'az-notifyrules-for-woocommerce' ) ), 400 );
		}

		$rules          = $this->plugin->rules();
		$requested_id   = sanitize_key( $raw['id'] ?? '' );
		$existing_index = null;
		if ( '' !== $requested_id ) {
			foreach ( $rules as $index => $existing_rule ) {
				if ( $requested_id === (string) ( $existing_rule['id'] ?? '' ) ) {
					$existing_index = $index;
					break;
				}
			}
			if ( null === $existing_index ) {
				wp_send_json_error( array( 'message' => __( 'This rule no longer exists.', 'az-notifyrules-for-woocommerce' ) ), 404 );
			}
		}

		$rule = $this->sanitize_rule( $raw, array_keys( $this->plugin->channels_by_id() ) );
		if ( is_wp_error( $rule ) ) {
			wp_send_json_error( array( 'message' => $rule->get_error_message() ), 422 );
		}

		if ( null === $existing_index ) {
			$rules[] = $rule;
		} else {
			$rules[ $existing_index ] = $rule;
		}
		$rules = array_values( $rules );

		$updated = update_option( AZ_Woo_Alerts::OPTION_RULES, $rules, false );
		if ( ! $updated && $rules !== get_option( AZ_Woo_Alerts::OPTION_RULES, array() ) ) {
			wp_send_json_error( array( 'message' => __( 'WordPress could not save the rule.', 'az-notifyrules-for-woocommerce' ) ), 500 );
		}

		$channels   = $this->plugin->channels_by_id();
		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$categories = is_wp_error( $categories ) ? array() : $categories;
		ob_start();
		$this->render_rule_display_row( (string) $rule['id'], $rule, $channels, $categories );
		$html = ob_get_clean();

		nocache_headers();
		wp_send_json_success(
			array(
				'id'      => (string) $rule['id'],
				'html'    => $html,
				'message' => __( 'The rule has been saved.', 'az-notifyrules-for-woocommerce' ),
			)
		);
	}

	private function render_channels(): void {
		$channels = $this->plugin->channels();
		?>
		<div class="az-channels-editor">
			<table class="widefat striped az-repeater" data-kind="channels">
				<thead><tr><th><?php esc_html_e( 'Status', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Connection', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Configuration', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Actions', 'az-notifyrules-for-woocommerce' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $channels as $channel ) : ?>
					<?php $this->render_channel_display_row( (string) ( $channel['id'] ?? '' ), $channel ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<script type="text/template" class="az-row-template"><?php $this->render_channel_edit_row( '__INDEX__', array( 'type' => 'pushover', 'enabled' => true, 'include_products' => false, 'include_amount' => false, 'include_customer' => false, 'priority' => 0 ) ); ?></script>
			<p><button type="button" class="button az-add-row"><?php esc_html_e( 'Add connection', 'az-notifyrules-for-woocommerce' ); ?></button></p>
		</div>
		<?php
	}

	private function render_channel_display_row( string $index, array $channel ): void {
		$id       = (string) ( $channel['id'] ?? '' );
		$type     = (string) ( $channel['type'] ?? '' );
		$provider = $this->plugin->providers()[ $type ] ?? null;
		?>
		<tr class="az-repeater-row az-channel-row az-channel-display-row" data-index="<?php echo esc_attr( $index ); ?>" data-channel-id="<?php echo esc_attr( $id ); ?>" data-channel-name="<?php echo esc_attr( $channel['name'] ?? '' ); ?>">
			<td><span class="az-channel-status"><?php echo ! empty( $channel['enabled'] ) ? esc_html__( 'Active', 'az-notifyrules-for-woocommerce' ) : esc_html__( 'Inactive', 'az-notifyrules-for-woocommerce' ); ?></span></td>
			<td><strong class="az-channel-name"><?php echo esc_html( $channel['name'] ?? '' ); ?></strong><br><span><?php echo esc_html( $provider ? $provider->get_label() : $type ); ?></span></td>
			<td><div class="az-channel-summary"><?php $this->render_channel_summary( $channel ); ?></div></td>
			<td>
				<?php if ( $id ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=az_woo_alerts_test_channel&channel=' . rawurlencode( $id ) ), 'az_woo_alerts_test_' . $id ) ); ?>"><?php esc_html_e( 'Test', 'az-notifyrules-for-woocommerce' ); ?></a><?php endif; ?>
				<button type="button" class="button az-edit-channel"><?php esc_html_e( 'Edit', 'az-notifyrules-for-woocommerce' ); ?></button>
				<button type="button" class="button-link-delete az-remove-row"><?php esc_html_e( 'Delete', 'az-notifyrules-for-woocommerce' ); ?></button>
			</td>
		</tr>
		<?php
	}

	private function render_channel_edit_row( string $index, array $channel ): void {
		$type = (string) ( $channel['type'] ?? 'pushover' );
		$id   = (string) ( $channel['id'] ?? '' );
		$app_token_configured = ! empty( $channel['app_token'] );
		$user_key_configured  = ! empty( $channel['user_key'] );
		$current_device       = (string) ( $channel['device'] ?? '' );
		$pushover_devices     = array();
		$devices_error        = '';

		if ( 'pushover' === $type && $app_token_configured && $user_key_configured ) {
			$provider = $this->plugin->providers()['pushover'] ?? null;
			if ( $provider instanceof AZ_Woo_Alerts_Pushover_Provider ) {
				$devices_result = $provider->get_devices( $channel );
				if ( is_wp_error( $devices_result ) ) {
					$devices_error = $devices_result->get_error_message();
				} else {
					$pushover_devices = $devices_result;
				}
			}
		}
		?>
		<tr class="az-repeater-row az-channel-row az-channel-edit-row" data-index="<?php echo esc_attr( $index ); ?>">
			<td><input type="checkbox" name="channels[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $channel['enabled'] ) ); ?>></td>
			<td>
				<input type="hidden" name="channels[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
				<label><?php esc_html_e( 'Name', 'az-notifyrules-for-woocommerce' ); ?><br><input type="text" class="regular-text" name="channels[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $channel['name'] ?? '' ); ?>" required></label><br>
				<label><?php esc_html_e( 'Type', 'az-notifyrules-for-woocommerce' ); ?><br><select class="az-channel-type" name="channels[<?php echo esc_attr( $index ); ?>][type]">
					<?php foreach ( $this->plugin->providers() as $provider_type => $provider ) : ?>
						<option value="<?php echo esc_attr( $provider_type ); ?>" <?php selected( $type, $provider_type ); ?>><?php echo esc_html( $provider->get_label() ); ?></option>
					<?php endforeach; ?>
				</select></label>
			</td>
			<td>
				<div class="az-channel-fields az-channel-pushover" <?php echo 'pushover' === $type ? '' : 'hidden'; ?>>
					<p class="description"><?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: Pushover privacy policy URL, 2: Pushover terms URL. */
								__( 'When enabled, matching order data is sent to Pushover over HTTPS. Optional order details are controlled below. See the Pushover <a href="%1$s" target="_blank" rel="noopener noreferrer">privacy policy</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">terms</a>.', 'az-notifyrules-for-woocommerce' ),
								esc_url( 'https://pushover.net/privacy' ),
								esc_url( 'https://pushover.net/terms' )
							)
						);
					?></p>
					<div class="az-grid">
						<label><?php esc_html_e( 'Application token', 'az-notifyrules-for-woocommerce' ); ?><span class="az-secret-control"><input type="text" autocomplete="off" class="az-secret-input" name="channels[<?php echo esc_attr( $index ); ?>][app_token]" value="<?php echo esc_attr( $app_token_configured ? AZ_Woo_Alerts_Crypto::STORED_MASK : '' ); ?>" data-configured="<?php echo $app_token_configured ? '1' : '0'; ?>"><?php if ( $app_token_configured && $id ) : ?><button type="button" class="button az-reveal-secret" data-channel="<?php echo esc_attr( $id ); ?>" data-field="app_token"><?php esc_html_e( 'Show', 'az-notifyrules-for-woocommerce' ); ?></button><?php endif; ?></span></label>
						<label><?php esc_html_e( 'User or group key', 'az-notifyrules-for-woocommerce' ); ?><span class="az-secret-control"><input type="text" autocomplete="off" class="az-secret-input" name="channels[<?php echo esc_attr( $index ); ?>][user_key]" value="<?php echo esc_attr( $user_key_configured ? AZ_Woo_Alerts_Crypto::STORED_MASK : '' ); ?>" data-configured="<?php echo $user_key_configured ? '1' : '0'; ?>"><?php if ( $user_key_configured && $id ) : ?><button type="button" class="button az-reveal-secret" data-channel="<?php echo esc_attr( $id ); ?>" data-field="user_key"><?php esc_html_e( 'Show', 'az-notifyrules-for-woocommerce' ); ?></button><?php endif; ?></span></label>
						<label><?php esc_html_e( 'Destination device', 'az-notifyrules-for-woocommerce' ); ?><select name="channels[<?php echo esc_attr( $index ); ?>][device]">
							<option value="" <?php selected( $current_device, '' ); ?>><?php esc_html_e( 'All active devices', 'az-notifyrules-for-woocommerce' ); ?></option>
							<?php foreach ( $pushover_devices as $device ) : ?>
								<option value="<?php echo esc_attr( $device ); ?>" <?php selected( $current_device, $device ); ?>><?php echo esc_html( $device ); ?></option>
							<?php endforeach; ?>
							<?php if ( '' !== $current_device && ! in_array( $current_device, $pushover_devices, true ) ) : ?>
									<option value="<?php echo esc_attr( $current_device ); ?>" selected><?php /* translators: %s: Pushover device name. */ echo esc_html( sprintf( __( '%s (not currently found)', 'az-notifyrules-for-woocommerce' ), $current_device ) ); ?></option>
							<?php endif; ?>
						</select><?php if ( $devices_error ) : ?><small class="description"><?php echo esc_html( $devices_error ); ?></small><?php elseif ( $pushover_devices ) : ?><small class="description"><?php esc_html_e( 'List retrieved from Pushover.', 'az-notifyrules-for-woocommerce' ); ?></small><?php elseif ( ! $app_token_configured || ! $user_key_configured ) : ?><small class="description"><?php esc_html_e( 'Save the credentials first to load devices.', 'az-notifyrules-for-woocommerce' ); ?></small><?php endif; ?></label>
						<label><?php esc_html_e( 'Title', 'az-notifyrules-for-woocommerce' ); ?><input type="text" name="channels[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $channel['title'] ?? __( 'New WooCommerce order', 'az-notifyrules-for-woocommerce' ) ); ?>"></label>
						<label><?php esc_html_e( 'Priority', 'az-notifyrules-for-woocommerce' ); ?><select name="channels[<?php echo esc_attr( $index ); ?>][priority]"><option value="-1" <?php selected( (int) ( $channel['priority'] ?? 0 ), -1 ); ?>><?php esc_html_e( 'Low', 'az-notifyrules-for-woocommerce' ); ?></option><option value="0" <?php selected( (int) ( $channel['priority'] ?? 0 ), 0 ); ?>><?php esc_html_e( 'Normal', 'az-notifyrules-for-woocommerce' ); ?></option><option value="1" <?php selected( (int) ( $channel['priority'] ?? 0 ), 1 ); ?>><?php esc_html_e( 'High', 'az-notifyrules-for-woocommerce' ); ?></option></select></label>
						<label><?php esc_html_e( 'Sound', 'az-notifyrules-for-woocommerce' ); ?><select name="channels[<?php echo esc_attr( $index ); ?>][sound]"><?php foreach ( $this->sounds() as $sound => $label ) : ?><option value="<?php echo esc_attr( $sound ); ?>" <?php selected( (string) ( $channel['sound'] ?? '' ), $sound ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
					</div>
					<label><input type="checkbox" name="channels[<?php echo esc_attr( $index ); ?>][include_products]" value="1" <?php checked( ! empty( $channel['include_products'] ) ); ?>> <?php esc_html_e( 'Include products', 'az-notifyrules-for-woocommerce' ); ?></label>
					<label><input type="checkbox" name="channels[<?php echo esc_attr( $index ); ?>][include_amount]" value="1" <?php checked( ! empty( $channel['include_amount'] ) ); ?>> <?php esc_html_e( 'Include order amount', 'az-notifyrules-for-woocommerce' ); ?></label>
					<label><input type="checkbox" name="channels[<?php echo esc_attr( $index ); ?>][include_customer]" value="1" <?php checked( ! empty( $channel['include_customer'] ) ); ?>> <?php esc_html_e( 'Include customer name', 'az-notifyrules-for-woocommerce' ); ?></label>
				</div>
				<div class="az-channel-fields az-channel-email" <?php echo 'email' === $type ? '' : 'hidden'; ?>>
					<label><?php esc_html_e( 'Addresses, separated by commas', 'az-notifyrules-for-woocommerce' ); ?><br><textarea rows="2" class="large-text" name="channels[<?php echo esc_attr( $index ); ?>][emails]"><?php echo esc_textarea( implode( ', ', (array) ( $channel['emails'] ?? array() ) ) ); ?></textarea></label>
					<label><?php esc_html_e( 'Header', 'az-notifyrules-for-woocommerce' ); ?> <select name="channels[<?php echo esc_attr( $index ); ?>][mode]"><option value="to" <?php selected( $channel['mode'] ?? 'bcc', 'to' ); ?>>To</option><option value="cc" <?php selected( $channel['mode'] ?? 'bcc', 'cc' ); ?>>CC</option><option value="bcc" <?php selected( $channel['mode'] ?? 'bcc', 'bcc' ); ?>>BCC</option></select></label>
				</div>
				<?php foreach ( $this->plugin->providers() as $provider_type => $provider ) : ?>
					<?php if ( in_array( $provider_type, array( 'pushover', 'email' ), true ) ) { continue; } ?>
					<div class="az-channel-fields az-channel-<?php echo esc_attr( $provider_type ); ?>" <?php echo $provider_type === $type ? '' : 'hidden'; ?>>
						<?php do_action( 'az_woo_alerts_channel_admin_fields_' . $provider_type, $index, $channel, $provider ); ?>
					</div>
				<?php endforeach; ?>
			</td>
			<td>
				<button type="button" class="button button-primary az-save-channel"><?php esc_html_e( 'Save', 'az-notifyrules-for-woocommerce' ); ?></button>
				<button type="button" class="button-link az-cancel-channel"><?php esc_html_e( 'Cancel', 'az-notifyrules-for-woocommerce' ); ?></button>
			</td>
		</tr>
		<?php
	}

	private function render_channel_summary( array $channel ): void {
		$type = (string) ( $channel['type'] ?? '' );
		if ( 'email' === $type ) {
			$modes = array( 'to' => 'To', 'cc' => 'CC', 'bcc' => 'BCC' );
			$mode  = $modes[ (string) ( $channel['mode'] ?? 'bcc' ) ] ?? 'BCC';
			?>
			<div><strong><?php echo esc_html( $mode ); ?> :</strong> <span><?php echo esc_html( implode( ', ', (array) ( $channel['emails'] ?? array() ) ) ); ?></span></div>
			<?php
			return;
		}

		if ( 'pushover' === $type ) {
			$priorities = array(
				-1 => __( 'Low', 'az-notifyrules-for-woocommerce' ),
				0  => __( 'Normal', 'az-notifyrules-for-woocommerce' ),
				1  => __( 'High', 'az-notifyrules-for-woocommerce' ),
			);
			$contents = array();
			if ( ! empty( $channel['include_products'] ) ) {
				$contents[] = __( 'products', 'az-notifyrules-for-woocommerce' );
			}
			if ( ! empty( $channel['include_amount'] ) ) {
				$contents[] = __( 'amount', 'az-notifyrules-for-woocommerce' );
			}
			if ( ! empty( $channel['include_customer'] ) ) {
				$contents[] = __( 'customer', 'az-notifyrules-for-woocommerce' );
			}
			$sound = (string) ( $channel['sound'] ?? '' );
			?>
			<div><strong><?php esc_html_e( 'Token and key:', 'az-notifyrules-for-woocommerce' ); ?></strong> <span class="az-secret-mask"><?php echo esc_html( AZ_Woo_Alerts_Crypto::STORED_MASK ); ?></span></div>
			<div><strong><?php esc_html_e( 'Device:', 'az-notifyrules-for-woocommerce' ); ?></strong> <span><?php echo esc_html( (string) ( $channel['device'] ?? '' ) ?: __( 'All active devices', 'az-notifyrules-for-woocommerce' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Priority:', 'az-notifyrules-for-woocommerce' ); ?></strong> <span><?php echo esc_html( $priorities[ (int) ( $channel['priority'] ?? 0 ) ] ?? $priorities[0] ); ?></span> · <strong><?php esc_html_e( 'Sound:', 'az-notifyrules-for-woocommerce' ); ?></strong> <span><?php echo esc_html( $this->sounds()[ $sound ] ?? $sound ); ?></span></div>
			<div><strong><?php esc_html_e( 'Content:', 'az-notifyrules-for-woocommerce' ); ?></strong> <span><?php echo esc_html( $contents ? implode( ', ', $contents ) : __( 'no details', 'az-notifyrules-for-woocommerce' ) ); ?></span></div>
			<?php
			return;
		}

		do_action( 'az_woo_alerts_channel_admin_summary_' . $type, $channel );
	}

	private function render_rules(): void {
		$rules      = $this->plugin->rules();
		$channels   = $this->plugin->channels_by_id();
		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$categories = is_wp_error( $categories ) ? array() : $categories;
		?>
		<?php if ( ! $channels ) : ?><div class="notice notice-warning inline"><p><?php esc_html_e( 'Create at least one connection first.', 'az-notifyrules-for-woocommerce' ); ?></p></div><?php endif; ?>
		<div class="az-rules-editor">
			<table class="widefat striped az-repeater" data-kind="rules">
				<thead><tr><th><?php esc_html_e( 'Status', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Rule', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Condition', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Connections', 'az-notifyrules-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Actions', 'az-notifyrules-for-woocommerce' ); ?></th></tr></thead>
				<tbody><?php foreach ( $rules as $rule ) : $this->render_rule_display_row( (string) ( $rule['id'] ?? '' ), $rule, $channels, $categories ); endforeach; ?></tbody>
			</table>
			<script type="text/template" class="az-row-template"><?php $this->render_rule_edit_row( '__INDEX__', array( 'enabled' => true, 'match_type' => 'all' ), $channels, $categories ); ?></script>
			<p><button type="button" class="button az-add-row" <?php disabled( ! $channels ); ?>><?php esc_html_e( 'Add rule', 'az-notifyrules-for-woocommerce' ); ?></button></p>
		</div>
		<?php
	}

	private function render_rule_display_row( string $index, array $rule, array $channels, array $categories ): void {
		$rule_json = wp_json_encode(
			array(
				'id'           => (string) ( $rule['id'] ?? '' ),
				'name'         => (string) ( $rule['name'] ?? '' ),
				'enabled'      => ! empty( $rule['enabled'] ),
				'match_type'   => (string) ( $rule['match_type'] ?? 'all' ),
				'match_values' => array_values( array_map( 'absint', (array) ( $rule['match_values'] ?? array() ) ) ),
				'match_options' => $this->rule_match_options( $rule, $categories ),
				'channel_ids'  => array_values( array_map( 'sanitize_key', (array) ( $rule['channel_ids'] ?? array() ) ) ),
			)
		);
		?>
		<tr class="az-repeater-row az-rule-row az-rule-display-row" data-index="<?php echo esc_attr( $index ); ?>" data-rule-id="<?php echo esc_attr( $rule['id'] ?? '' ); ?>" data-rule-name="<?php echo esc_attr( $rule['name'] ?? '' ); ?>" data-rule="<?php echo esc_attr( $rule_json ); ?>">
			<td><span class="az-rule-status"><?php echo ! empty( $rule['enabled'] ) ? esc_html__( 'Active', 'az-notifyrules-for-woocommerce' ) : esc_html__( 'Inactive', 'az-notifyrules-for-woocommerce' ); ?></span></td>
			<td><strong class="az-rule-name"><?php echo esc_html( $rule['name'] ?? '' ); ?></strong></td>
			<td><span class="az-rule-condition"><?php echo esc_html( $this->rule_condition_text( $rule, $categories ) ); ?></span></td>
			<td><div class="az-rule-connections"><?php
				$rendered = false;
				foreach ( (array) ( $rule['channel_ids'] ?? array() ) as $channel_id ) {
					if ( ! isset( $channels[ $channel_id ] ) ) {
						continue;
					}
					$rendered = true;
					echo '<span>' . esc_html( $channels[ $channel_id ]['name'] ) . ' <small>(' . esc_html( $channels[ $channel_id ]['type'] ) . ')</small></span><br>';
				}
				if ( ! $rendered ) {
					echo '&mdash;';
				}
			?></div></td>
			<td><button type="button" class="button az-edit-rule"><?php esc_html_e( 'Edit', 'az-notifyrules-for-woocommerce' ); ?></button> <button type="button" class="button-link-delete az-remove-row"><?php esc_html_e( 'Delete', 'az-notifyrules-for-woocommerce' ); ?></button></td>
		</tr>
		<?php
	}

	private function render_rule_edit_row( string $index, array $rule, array $channels, array $categories ): void {
		$type   = (string) ( $rule['match_type'] ?? 'all' );
		$values = array_map( 'absint', (array) ( $rule['match_values'] ?? array() ) );
		?>
		<tr class="az-repeater-row az-rule-row az-rule-edit-row" data-index="<?php echo esc_attr( $index ); ?>">
			<td><input type="checkbox" name="rules[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>></td>
			<td><input type="hidden" name="rules[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $rule['id'] ?? '' ); ?>"><input type="text" name="rules[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $rule['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Example: training materials', 'az-notifyrules-for-woocommerce' ); ?>" required></td>
			<td>
				<select class="az-match-type" name="rules[<?php echo esc_attr( $index ); ?>][match_type]"><option value="all" <?php selected( $type, 'all' ); ?>><?php esc_html_e( 'All orders', 'az-notifyrules-for-woocommerce' ); ?></option><option value="categories" <?php selected( $type, 'categories' ); ?>><?php esc_html_e( 'Categories', 'az-notifyrules-for-woocommerce' ); ?></option><option value="products" <?php selected( $type, 'products' ); ?>><?php esc_html_e( 'Products or variations', 'az-notifyrules-for-woocommerce' ); ?></option></select>
				<div class="az-condition az-condition-categories" <?php echo 'categories' === $type ? '' : 'hidden'; ?>><select multiple name="rules[<?php echo esc_attr( $index ); ?>][match_values][]"><?php foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( in_array( (int) $category->term_id, $values, true ) ); ?>><?php echo esc_html( $category->name ); ?></option><?php endforeach; ?></select></div>
				<div class="az-condition az-condition-products" <?php echo 'products' === $type ? '' : 'hidden'; ?>><select class="wc-product-search" multiple style="width:100%" name="rules[<?php echo esc_attr( $index ); ?>][match_values][]" data-placeholder="<?php esc_attr_e( 'Search for products…', 'az-notifyrules-for-woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations"><?php foreach ( $values as $product_id ) : $product = wc_get_product( $product_id ); if ( $product ) : ?><option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option><?php endif; endforeach; ?></select></div>
			</td>
			<td><?php foreach ( $channels as $channel_id => $channel ) : ?><label class="az-channel-choice"><input type="checkbox" name="rules[<?php echo esc_attr( $index ); ?>][channel_ids][]" value="<?php echo esc_attr( $channel_id ); ?>" <?php checked( in_array( $channel_id, (array) ( $rule['channel_ids'] ?? array() ), true ) ); ?>> <?php echo esc_html( $channel['name'] ); ?> <small>(<?php echo esc_html( $channel['type'] ); ?>)</small></label><?php endforeach; ?></td>
			<td><button type="button" class="button button-primary az-save-rule"><?php esc_html_e( 'Save', 'az-notifyrules-for-woocommerce' ); ?></button> <button type="button" class="button-link az-cancel-rule"><?php esc_html_e( 'Cancel', 'az-notifyrules-for-woocommerce' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * @param array<int,string> $channel_ids
	 * @return array<string,mixed>|WP_Error
	 */
	private function sanitize_rule( array $raw, array $channel_ids ) {
		$name       = sanitize_text_field( $raw['name'] ?? '' );
		$match_type = in_array( (string) ( $raw['match_type'] ?? 'all' ), array( 'all', 'categories', 'products' ), true ) ? (string) $raw['match_type'] : 'all';
		$values     = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $raw['match_values'] ?? array() ) ) ) ) );
		$selected   = array_values( array_intersect( $channel_ids, array_map( 'sanitize_key', (array) ( $raw['channel_ids'] ?? array() ) ) ) );

		if ( '' === $name ) {
			return new WP_Error( 'az_woo_alerts_rule_name', __( 'Each rule must have a name.', 'az-notifyrules-for-woocommerce' ) );
		}
		if ( 'all' !== $match_type && ! $values ) {
				/* translators: %s: rule name. */
				return new WP_Error( 'az_woo_alerts_rule_condition', sprintf( __( 'The “%s” rule does not contain any product or category.', 'az-notifyrules-for-woocommerce' ), $name ) );
		}
		if ( ! $selected ) {
				/* translators: %s: rule name. */
				return new WP_Error( 'az_woo_alerts_rule_channel', sprintf( __( 'The “%s” rule does not contain a connection.', 'az-notifyrules-for-woocommerce' ), $name ) );
		}

		return array(
			'id'           => sanitize_key( $raw['id'] ?? '' ) ?: 'rule_' . wp_generate_uuid4(),
			'name'         => $name,
			'enabled'      => ! empty( $raw['enabled'] ),
			'match_type'   => $match_type,
			'match_values' => $values,
			'channel_ids'  => $selected,
		);
	}

	private function rule_condition_text( array $rule, array $categories ): string {
		$type   = (string) ( $rule['match_type'] ?? 'all' );
		$values = array_map( 'absint', (array) ( $rule['match_values'] ?? array() ) );
		if ( 'all' === $type ) {
			return __( 'All orders', 'az-notifyrules-for-woocommerce' );
		}

		$names = array();
		if ( 'categories' === $type ) {
			foreach ( $categories as $category ) {
				if ( in_array( (int) $category->term_id, $values, true ) ) {
					$names[] = (string) $category->name;
				}
			}
				/* translators: %s: comma-separated category names. */
				return sprintf( __( 'Categories: %s', 'az-notifyrules-for-woocommerce' ), implode( ', ', $names ) );
		}

		foreach ( $values as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$names[] = wp_strip_all_tags( $product->get_formatted_name() );
			}
		}
			/* translators: %s: comma-separated product names. */
			return sprintf( __( 'Products: %s', 'az-notifyrules-for-woocommerce' ), implode( ', ', $names ) );
	}

	/** @return array<int,array{id:string,text:string}> */
	private function rule_match_options( array $rule, array $categories ): array {
		$type    = (string) ( $rule['match_type'] ?? 'all' );
		$values  = array_map( 'absint', (array) ( $rule['match_values'] ?? array() ) );
		$options = array();

		if ( 'categories' === $type ) {
			foreach ( $categories as $category ) {
				if ( in_array( (int) $category->term_id, $values, true ) ) {
					$options[] = array(
						'id'   => (string) $category->term_id,
						'text' => (string) $category->name,
					);
				}
			}
			return $options;
		}

		if ( 'products' === $type ) {
			foreach ( $values as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$options[] = array(
						'id'   => (string) $product_id,
						'text' => wp_strip_all_tags( $product->get_formatted_name() ),
					);
				}
			}
		}

		return $options;
	}

	private function render_notices(): void {
		$key    = 'az_woo_alerts_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );
		$type = 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>';
	}

	private function set_notice( string $type, string $message ): void {
		set_transient( 'az_woo_alerts_notice_' . get_current_user_id(), compact( 'type', 'message' ), MINUTE_IN_SECONDS );
	}

	private function redirect( string $tab ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=az-notifyrules-for-woocommerce&tab=' . $tab ) );
		exit;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these alerts.', 'az-notifyrules-for-woocommerce' ) );
		}
	}

	/** @return array<string,string> */
	private function sounds(): array {
		return array(
			''             => __( 'Default sound', 'az-notifyrules-for-woocommerce' ),
			'pushover'     => 'Pushover',
			'cashregister' => __( 'Cash register', 'az-notifyrules-for-woocommerce' ),
			'incoming'     => 'Incoming',
			'magic'        => 'Magic',
			'mechanical'   => 'Mechanical',
			'spacealarm'   => 'Space Alarm',
			'siren'        => 'Siren',
			'vibrate'      => __( 'Vibrate only', 'az-notifyrules-for-woocommerce' ),
			'none'         => __( 'Silent', 'az-notifyrules-for-woocommerce' ),
		);
	}
}
