<?php
/**
 * Plugin Name: WooCommerce TradeSafe Gateway
 * Plugin URI: https://github.com/tradesafe-plugins/woocommerce-tradesafe-gateway
 * Description: Receive payments using the TradeSafe API.
 * Author: TradeSafe
 * Author URI: http://www.tradesafe.co.za/
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 5.0
 * WC tested up to: 3.5
 * WC requires at least: 3.5
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function woocommerce_tradesafe_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	define( 'WC_GATEWAY_TRADESAFE_VERSION', '1.0.0' );

	require_once( plugin_basename( 'includes/class-wc-gateway-tradesafe.php' ) );
	require_once( plugin_basename( 'includes/class-wc-gateway-tradesafe-privacy.php' ) );
	load_plugin_textdomain( 'woocommerce-gateway-tradesafe', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_tradesafe_add_gateway' );
}
add_action( 'plugins_loaded', 'woocommerce_tradesafe_init', 0 );

function woocommerce_tradesafe_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'wc_gateway_tradesafe',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-gateway-tradesafe' ) . '</a>',
		'<a href="https://www.tradesafe.co.za/page/contact">' . __( 'Support', 'woocommerce-gateway-tradesafe' ) . '</a>',
		'<a href="https://www.tradesafe.co.za/page/API">' . __( 'Docs', 'woocommerce-gateway-tradesafe' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_tradesafe_plugin_links' );

add_action( 'woocommerce_cancelled_order', 'order_status_cancelled');
function order_status_cancelled($order) {
//    print_r(get_defined_vars());
//    die();
}

add_action( 'show_user_profile', 'edit_tradesafe_profile' );
add_action( 'edit_user_profile', 'edit_tradesafe_profile' );
function edit_tradesafe_profile($user) {
    $id_number = get_the_author_meta( 'id_number', $user->ID );
    $bank = get_the_author_meta( 'bank', $user->ID );
    $account_number = get_the_author_meta( 'account_number', $user->ID );
    $branch_code = get_the_author_meta( 'branch_code', $user->ID );
    $account_type = get_the_author_meta( 'account_type', $user->ID );
    ?>
    <h3><?php esc_html_e( 'Personal Information', 'tradesafe' ); ?></h3>

    <table class="form-table">
        <tr>
            <th><label for="id_number"><?php esc_html_e( 'ID Number', 'tradesafe' ); ?></label></th>
            <td>
                <input type="text"
                       id="id_number"
                       name="id_number"
                       value="<?php echo esc_attr( $id_number ); ?>"
                       class="regular-text"
                />
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e( 'Banking Details', 'tradesafe' ); ?></h3>

    <table class="form-table">
        <tr>
            <th><label for="bank"><?php esc_html_e( 'Bank Name', 'tradesafe' ); ?></label></th>
            <td>
                <input type="text"
                       id="bank"
                       name="bank"
                       value="<?php echo esc_attr( $bank ); ?>"
                       class="regular-text"
                />
            </td>
        </tr>

        <tr>
            <th><label for="account_number"><?php esc_html_e( 'Account Number', 'tradesafe' ); ?></label></th>
            <td>
                <input type="text"
                       id="account_number"
                       name="account_number"
                       value="<?php echo esc_attr( $account_number ); ?>"
                       class="regular-text"
                />
            </td>
        </tr>

        <tr>
            <th><label for="branch_code"><?php esc_html_e( 'Branch Code', 'tradesafe' ); ?></label></th>
            <td>
                <input type="text"
                       id="branch_code"
                       name="branch_code"
                       value="<?php echo esc_attr( $branch_code ); ?>"
                       class="regular-text"
                />
            </td>
        </tr>

        <tr>
            <th><label for="account_type"><?php esc_html_e( 'Account Type', 'tradesafe' ); ?></label></th>
            <td>
                <input type="text"
                       id="account_type"
                       name="account_type"
                       value="<?php echo esc_attr( $account_type ); ?>"
                       class="regular-text"
                />
            </td>
        </tr>
    </table>

    <?php
}

add_action( 'personal_options_update', 'update_tradesafe_profile' );
add_action( 'edit_user_profile_update', 'update_tradesafe_profile' );
function update_tradesafe_profile( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( ! empty( $_POST['id_number'] ) ) {
        update_user_meta( $user_id, 'id_number', intval( $_POST['id_number'] ) );
    }

    if ( ! empty( $_POST['bank'] ) ) {
        update_user_meta( $user_id, 'bank', intval( $_POST['bank'] ) );
    }

    if ( ! empty( $_POST['account_number'] ) ) {
        update_user_meta( $user_id, 'account_number', intval( $_POST['account_number'] ) );
    }

    if ( ! empty( $_POST['branch_code'] ) ) {
        update_user_meta( $user_id, 'branch_code', intval( $_POST['branch_code'] ) );
    }

    if ( ! empty( $_POST['account_type'] ) ) {
        update_user_meta( $user_id, 'account_type', intval( $_POST['account_type'] ) );
    }
}

/**
 * Cancel Order
 */
add_action('woocommerce_order_status_pending_to_cancelled', 'woocommerce_tradesafe_cancel_order', 0, 1);
function woocommerce_tradesafe_cancel_order($order_id) {
    $order = wc_get_order( $order_id );

    if ($order->meta_exists('tradesafe_id')) {
        $data = array(
                'step' => 'DECLINED',
        );

        $response = woocommerce_tradesafe_api_request('contract/' . $order->get_meta('tradesafe_id'), array('body' => $data), 'PUT');
    }
}

/**
 * Send off API request.
 *
 * @since 1.4.0 introduced.
 *
 * @param $command
 * @param $token
 * @param $api_args
 * @param string $method GET | PUT | POST | DELETE.
 *
 * @return bool|WP_Error
 */
function woocommerce_tradesafe_api_request( $command, $api_args, $method = 'POST' ) {
    $settings = get_option('woocommerce_tradesafe_settings');
    $url = 'https://www.tradesafe.co.za/api';
    $token = $settings['json_web_token'];

    if ( 'yes' === $settings['testmode'] ) {
        $url = 'https://sandbox.tradesafe.co.za/api';
    }

    if ( empty( $token ) ) {
        error_log( "Error posting API request: No token supplied", true );
        return new WP_Error( '404', __( 'A token is required to submit a request to the TradeSafe API', 'woocommerce-gateway-tradesafe' ), null );
    }

    $api_endpoint  = sprintf('%s/%s', $url, $command);

    $api_args['timeout'] = 45;
    $api_args['headers'] = array(
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'   => 'application/json',
    );

    if (isset($api_args['body'])) {
        $api_args['body'] = json_encode($api_args['body']);
    }
    $api_args['method'] = strtoupper( $method );

    $results = wp_remote_request( $api_endpoint, $api_args );

    if (isset($results['response']['code']) && 200 !== $results['response']['code'] && 201 !== $results['response']['code']) {
        error_log( "Error posting API request:\n" . print_r( $results['response'], true ) );
        return new WP_Error( $results['response']['code'], $results['response']['message'], $results );
    }

    $maybe_json = json_decode( $results['body'], true );

    if ( ! is_null( $maybe_json ) && isset($maybe_json['error']) ) {
        error_log( "Error posting API request:\n" . print_r( $results['body'], true ) );

        // Use trim here to display it properly e.g. on an order note, since TradeSafe can include CRLF in a message.
        return new WP_Error( 422, trim( $maybe_json['error'] ), $results['body'] );
    }

    return $maybe_json;
}

/**
 * Add the gateway to WooCommerce
 * @since 1.0.0
 */
function woocommerce_tradesafe_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_TradeSafe';
	return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'woocommerce_tradesafe_valid_transaction' );
function woocommerce_tradesafe_valid_transaction($available_gateways) {
    $user = wp_get_current_user();
    $user_id = get_user_meta($user->id, 'tradesafe_id', true);

    if (!$user_id && isset($available_gateways['tradesafe'])) {
        unset($available_gateways['tradesafe']);
    }

    return $available_gateways;
}

/*
 * Add Link (Tab) to My Account menu
 */
add_filter ( 'woocommerce_account_menu_items', 'woocommerce_tradesafe_account_tab', 40 );
function woocommerce_tradesafe_account_tab($menu_links){
    $menu_links = array_slice( $menu_links, 0, 5, true )
        + array( 'tradesafe' => 'TradeSafe Details' )
        + array_slice( $menu_links, 5, NULL, true );

    return $menu_links;
}

add_action( 'init', 'woocommerce_tradesafe_account' );
function woocommerce_tradesafe_account() {
    add_rewrite_endpoint( 'tradesafe', EP_PAGES );
}

add_action( 'woocommerce_account_tradesafe_endpoint', 'woocommerce_tradesafe_account_content' );
function woocommerce_tradesafe_account_content() {
    $settings = get_option('woocommerce_tradesafe_settings');
    $url = 'https://www.tradesafe.co.za/api';
    $token = $settings['json_web_token'];

    if ( 'yes' === $settings['testmode'] ) {
        $url = 'https://sandbox.tradesafe.co.za/api';
    }

    $user = wp_get_current_user();
    $user_id = get_user_meta($user->id, 'tradesafe_id', true);

    if (!$user_id) {
        $api_endpoint  = sprintf('%s/%s', $url, 'user/' . $user->user_email);
    } else {
        $api_endpoint  = sprintf('%s/%s', $url, 'user/' . $user_id);
    }

    $api_args['timeout'] = 45;
    $api_args['headers'] = array(
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'   => 'application/json',
    );

    $response = wp_remote_request($api_endpoint, $api_args);

    if ( ! is_wp_error( $response ) ) {
        if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
            $body = json_decode($response['body']);
            add_user_meta($user->id, 'tradesafe_id', $body->User->username, true);
            print "Account linked!";
        } else {
            print 'Could not link TradeSafe Account';
        }
    } else {
        print 'Could not link TradeSafe Account';
    }
}