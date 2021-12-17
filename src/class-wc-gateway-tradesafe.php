<?php
/**
 * TradeSafe Gateway for WooCommerce.
 *
 * @package TradeSafe Payment Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_TradeSafe Implantation of WC_Payment_Gateway
 */
class WC_Gateway_TradeSafe extends WC_Payment_Gateway {

	/**
	 * Api Client
	 *
	 * @var string
	 */
	public $client;

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'tradesafe';
		$this->method_title       = __( 'TradeSafe Escrow', 'tradesafe-payment-gateway' );
		$this->method_description = __( 'TradeSafe keeps the funds (in trust) until you get what you’re paying for. Pay with Ozow, credit/debit card, EFT, or SnapScan', 'tradesafe-payment-gateway' );
		$this->icon               = TRADESAFE_PAYMENT_GATEWAY_BASE_DIR . '/assets/images/icon.svg';

		$this->client = new \TradeSafe\Helpers\TradeSafeApiClient();

		$this->version              = WC_GATEWAY_TRADESAFE_VERSION;
		$this->available_countries  = array( 'ZA' );
		$this->available_currencies = (array) apply_filters( 'woocommerce_gateway_tradesafe_available_currencies', array( 'ZAR' ) );

		// Supported functionality.
		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		// Setup default merchant data.
		$this->has_fields  = true;
		$this->enabled     = $this->is_valid_for_use() ? 'yes' : 'no'; // Check if the base currency supports this gateway.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_tradesafe', array( $this, 'receipt_page' ) );

		if ( is_admin() ) {
			wp_enqueue_script( 'tradesafe-payment-gateway-settings', TRADESAFE_PAYMENT_GATEWAY_BASE_DIR . '/assets/js/settings.js', array( 'jquery' ), WC_GATEWAY_TRADESAFE_VERSION, true );
			wp_enqueue_style( 'tradesafe-payment-gateway-settings', TRADESAFE_PAYMENT_GATEWAY_BASE_DIR . '/assets/css/style.css', array(), WC_GATEWAY_TRADESAFE_VERSION );
		}
	}

	/**
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_valid_for_use() {
		$is_available          = false;
		$is_available_currency = in_array( get_woocommerce_currency(), $this->available_currencies, true );

		if ( $is_available_currency
			&& get_option( 'tradesafe_client_id' )
			&& get_option( 'tradesafe_client_secret' ) ) {
			$is_available = true;
		}

		if ( 'no' === $this->get_option( 'enabled' ) || null === $this->get_option( 'enabled' ) ) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Define Gateway settings fields.
	 */
	public function init_form_fields() {
		$form = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'tradesafe-payment-gateway' ),
				'label'       => __( 'Enable TradeSafe', 'tradesafe-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'tradesafe-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'title'       => array(
				'title'       => __( 'Title', 'tradesafe-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'tradesafe-payment-gateway' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'tradesafe-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'tradesafe-payment-gateway' ),
				'default'     => $this->method_description,
				'desc_tip'    => true,
			),
		);

		$form['setup_details'] = array(
			'title'       => __( 'Callback Details', 'tradesafe-payment-gateway' ),
			'description' => __( 'The following urls are used when registering your application with TradeSafe.', 'tradesafe-payment-gateway' ),
			'type'        => 'setup_details',
		);

		$form['debug_details'] = array(
			'title'       => __( 'Plugin Details', 'tradesafe-payment-gateway' ),
			'description' => __( 'Various details about your WordPress install used for debugging and support.', 'tradesafe-payment-gateway' ),
			'type'        => 'debug_details',
		);

		$form['application_details'] = array(
			'title'       => __( 'Application Details', 'tradesafe-payment-gateway' ),
			'description' => __( 'Details of your application registered with TradeSafe.', 'tradesafe-payment-gateway' ),
			'type'        => 'application_details',
		);

		$form['application_section_title'] = array(
			'title'       => __( 'Application Settings', 'tradesafe-payment-gateway' ),
			'type'        => 'title',
			'description' => __( 'API configuration', 'tradesafe-payment-gateway' ),
		);

		$form['client_id'] = array(
			'title'       => __( 'Client ID', 'tradesafe-payment-gateway' ),
			'type'        => 'text',
			'description' => __( 'Client ID for your application.', 'tradesafe-payment-gateway' ),
			'default'     => null,
			'desc_tip'    => true,
		);

		$form['client_secret'] = array(
			'title'       => __( 'Client Secret', 'tradesafe-payment-gateway' ),
			'type'        => 'password',
			'description' => __( 'Client secret for your application.', 'tradesafe-payment-gateway' ),
			'default'     => null,
			'desc_tip'    => true,
		);

		if ( $this->client->production() ) {
			$form['environment'] = array(
				'title'    => __( 'Environment', 'tradesafe-payment-gateway' ),
				'type'     => 'select',
				'default'  => 'SIT',
				'options'  => array(
					'SIT'  => 'Sandbox',
					'PROD' => 'Live',
				),
				'desc_tip' => false,
			);
		} else {
			$form['environment'] = array(
				'title'       => __( 'Environment', 'tradesafe-payment-gateway' ),
				'type'        => 'row',
				'description' => __( 'To access the live environment, you will need to complete the go-live process for your application', 'tradesafe-payment-gateway' ),
				'value'       => 'Sandbox',
			);
		}

		$form['marketplace_section_title'] = array(
			'title'       => __( 'Marketplace Settings', 'tradesafe-payment-gateway' ),
			'type'        => 'title',
			'description' => __( 'Additional settings for creating a marketplace', 'tradesafe-payment-gateway' ),
		);

		$form['is_marketplace'] = array(
			'title'       => __( 'Is this website a Marketplace?', 'tradesafe-payment-gateway' ),
			'label'       => 'Enable Marketplace Support',
			'type'        => 'checkbox',
			'description' => __( 'You are a marketplace owner who is paid a commission and has multiple vendors onboarded onto your store', 'tradesafe-payment-gateway' ),
			'default'     => false,
			'desc_tip'    => false,
			'class'       => 'test',
		);

		$form['marketplace_section_open_box'] = array(
			'type'  => 'open_box',
			'class' => 'is-marketplace',
		);

		$form['buyers_accept'] = array(
			'title'    => __( 'Allow buyers to accept goods to release funds', 'tradesafe-payment-gateway' ),
			'label'    => 'Show Accept Button',
			'type'     => 'checkbox',
			'default'  => true,
			'desc_tip' => false,
		);

		$form['commission'] = array(
			'title'       => __( 'Marketplace Commission Fee', 'tradesafe-payment-gateway' ),
			'type'        => 'number',
			'description' => __( 'What is the amount that is payable to you the marketplace owner for every transaction', 'tradesafe-payment-gateway' ),
			'default'     => 10,
			'desc_tip'    => false,
		);

		$form['commission_type'] = array(
			'title'    => __( 'Marketplace Commission Type', 'tradesafe-payment-gateway' ),
			'type'     => 'select',
			'default'  => 'PERCENT',
			'options'  => array(
				'PERCENT' => 'Percentage',
				'FIXED'   => 'Fixed Value',
			),
			'desc_tip' => false,
		);

		$form['commission_allocation'] = array(
			'title'    => __( 'Marketplace Commission Fee Allocation', 'tradesafe-payment-gateway' ),
			'type'     => 'select',
			'default'  => 'VENDOR',
			'options'  => array(
				'BUYER'  => 'Buyer',
				'VENDOR' => 'Vendor',
			),
			'desc_tip' => false,
		);

		if ( tradesafe_has_dokan() ) {
			$form['commission'] = array(
				'title'       => __( 'Marketplace Commission Fee', 'tradesafe-payment-gateway' ),
				'description' => __( 'What is the amount that is payable to you the marketplace owner for every transaction.', 'tradesafe-payment-gateway' ),
				'type'        => 'row',
				'value'       => dokan_get_option( 'admin_percentage', 'dokan_selling', 0 ),
			);

			$form['commission_type'] = array(
				'title'       => __( 'Marketplace Commission Type', 'tradesafe-payment-gateway' ),
				'description' => __( 'What type of commission been changed', 'tradesafe-payment-gateway' ),
				'type'        => 'row',
				'value'       => ucwords( dokan_get_option( 'commission_type', 'dokan_selling', 'percentage' ) ),
			);

			$form['commission_allocation'] = array(
				'title'       => __( 'Marketplace Commission Fee Allocation', 'tradesafe-payment-gateway' ),
				'description' => __( 'Why will pay the commission' ),
				'type'        => 'row',
				'value'       => 'Vendor',
			);
		}

		$form['marketplace_section_close_box'] = array(
			'type' => 'close_box',
		);

		$form['transaction_section_title'] = array(
			'title'       => __( 'Transaction Settings', 'tradesafe-payment-gateway' ),
			'type'        => 'title',
			'description' => __( 'Default settings for new transactions', 'tradesafe-payment-gateway' ),
		);

		$form['industry'] = array(
			'title'       => __( 'Industry', 'tradesafe-payment-gateway' ),
			'type'        => 'select',
			'description' => __( 'Which industry will your transactions be classified as?', 'tradesafe-payment-gateway' ),
			'default'     => 'GENERAL_GOODS_SERVICES',
			'options'     => $this->client->getEnum( 'Industry' ),
			'desc_tip'    => false,
		);

		$form['processing_fee'] = array(
			'title'       => __( 'Processing Fee', 'tradesafe-payment-gateway' ),
			'type'        => 'select',
			'description' => __( 'Who absorbs TradeSafe’s fee?', 'tradesafe-payment-gateway' ),
			'default'     => 'SELLER',
			'options'     => array(
				'BUYER'        => 'Buyer',
				'SELLER'       => 'Seller',
				'BUYER_SELLER' => 'Buyer / Seller',
			),
			'desc_tip'    => false,
		);

		$this->form_fields = $form;
	}

	/**
	 * Init settings for gateways.
	 */
	public function init_settings() {
		parent::init_settings();
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		delete_transient( 'tradesafe_client_token' );

		return parent::process_admin_options();
	}

	/**
	 * Create html for the details needed to setup the application.
	 *
	 * @var $key string
	 * @var $data array
	 */
	public function generate_setup_details_html( string $key, array $data ) {
		$urls = array(
			'oauth_callback' => site_url( '/tradesafe/oauth/callback/' ),
			'callback'       => site_url( '/tradesafe/callback/' ),
			'success'        => wc_get_endpoint_url( 'orders', '', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ),
			'failure'        => wc_get_endpoint_url( 'orders', '', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ),
		);

		ob_start();
		?>
		<tr>
			<td colspan="2" class="details">
				<div class="details-box callback-details">
					<h3><?php esc_attr_e( $data['title'] ); ?></h3>
					<p><?php esc_attr_e( $data['description'] ); ?></p>
					<table class="form-table">
						<tbody>
						<tr>
							<th scope="row">OAuth Callback URL</th>
							<td><?php esc_attr_e( $urls['oauth_callback'] ); ?></td>
						</tr>
						<tr>
							<th scope="row">API Callback URL</th>
							<td><?php esc_attr_e( $urls['callback'] ); ?></td>
						</tr>
						<tr>
							<th scope="row">Success URL</th>
							<td><?php esc_attr_e( $urls['success'] ); ?></td>
						</tr>
						<tr>
							<th scope="row">Failure URL</th>
							<td><?php esc_attr_e( $urls['failure'] ); ?></td>
						</tr>
						</tbody>
					</table>
					<p>
						<a href="https://developer.tradesafe.co.za/"
						   class="button-secondary button alt button-large button-next" target="_blank">Register
							Application</a>
					</p>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 *
	 */
	public function generate_debug_details_html( $key, $data ) {
		$ping_result = $this->client->ping();

		ob_start();
		?>
		<tr>
			<td colspan="2" class="details">
				<div class="details-box plugin-details">
					<h3><?php esc_attr_e( $data['title'] ); ?> <small>(<a href="#" class="toggle-plugin-details">show</a>)</small>
					</h3>
					<p><?php esc_attr_e( $data['description'] ); ?></p>
					<table class="form-table">
						<tbody>
						<tr>
							<th scope="row">PHP Version</th>
							<td><?php esc_attr_e( phpversion() ); ?></td>
						</tr>
						<tr>
							<th scope="row">WordPress Version</th>
							<td><?php esc_attr_e( get_bloginfo( 'version' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row">Woocommerce Version</th>
							<td><?php esc_attr_e( WC_VERSION ); ?></td>
						</tr>
						<tr>
							<th scope="row">Plugin Version</th>
							<td><?php esc_attr_e( WC_GATEWAY_TRADESAFE_VERSION ); ?></td>
						</tr>
						<tr>
							<th scope="row">API Domain</th>
							<td>
							<?php
							esc_attr_e( $ping_result['api']['domain'] );
								esc_attr_e( ' [' . ( $ping_result['api']['status'] ? 'OK' : 'ERROR' ) . ']' )
							?>
								</td>
						</tr>
						<?php
						if ( $ping_result['api']['reason'] ) {
							echo '<tr><th scope="row">API Error</th><td>' . esc_attr( $ping_result['api']['reason'] ) . '</td></tr>';
						}
						?>
						<tr>
							<th scope="row">Authentication Domain</th>
							<td>
							<?php
							esc_attr_e( $ping_result['auth']['domain'] );
								esc_attr_e( ' [' . ( $ping_result['auth']['status'] ? 'OK' : 'ERROR' ) . ']' )
							?>
								</td>
						</tr>
						<?php
						if ( $ping_result['auth']['reason'] ) {
							echo '<tr><th scope="row">Authentication Error</th><td>' . esc_attr( $ping_result['auth']['reason'] ) . '</td></tr>';
						}
						?>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 *
	 */
	public function generate_application_details_html( $key, $data ) {
		$profile = $this->client->profile();

		if ( isset( $profile['error'] ) ) {
			$body  = "<tr><th scope='row'>Error:</th><td> Could not connect to server</td></tr>";
			$body .= "<tr><th scope='row'>Reason:</th><td> " . esc_attr( $profile['error'] ) . '</td></tr>';
		} else {
			$body  = "<tr><th scope='row'>Organization Name:</th><td>" . esc_attr( $profile['organization']['name'] ) . '</td></tr>';
			$body .= "<tr><th scope='row'>Registration Number:</th><td>" . esc_attr( $profile['organization']['registration'] ) . '</td></tr>';

			if ( $profile['organization']['taxNumber'] ) {
				$body .= "<tr><th scope='row'>Tax Number:</th><td>" . esc_attr( $profile['organization']['taxNumber'] ) . '</td></tr>';
			}

			$body .= "<tr><th scope='row'>Name:</th><td>" . esc_attr( $profile['user']['givenName'] ) . ' ' . esc_attr( $profile['user']['familyName'] ) . '</td></tr>';
			$body .= "<tr><th scope='row'>Email:</th><td>" . esc_attr( $profile['user']['email'] ) . '</td></tr>';
			$body .= "<tr><th scope='row'>Mobile:</th><td>" . esc_attr( $profile['user']['mobile'] ) . '</td></tr>';

			$body .= "<tr><th scope='row'>Go-Live Completed:</th><td>" . esc_attr( $this->client->production() ? 'Yes' : 'No' ) . '</td></tr>';
		}

		ob_start();
		?>
		<tr>
			<td colspan="2" class="details">
				<div class="details-box application-details">
					<h3><?php esc_attr_e( $data['title'] ); ?></h3>
					<p><?php esc_attr_e( $data['description'] ); ?></p>
					<table class="form-table">
						<tbody>
						<?php echo $body; ?>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function generate_row_html( $key, $data ) {
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="woocommerce_tradesafe_environment"><?php esc_attr_e( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<?php esc_attr_e( $data['value'] ); ?>
					<p class="description"><?php esc_attr_e( $data['description'] ); ?></p>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function generate_open_box_html( $key, $data ) {
		ob_start();
		?>
		</tbody>
		</table>
		<div class="<?php esc_attr_e( $data['class'] ); ?>">
		<table class="form-table">
		<tbody>
		<?php
		return ob_get_clean();
	}

	public function generate_close_box_html( $key, $data ) {
		ob_start();
		?>
		</tbody>
		</table>
		</div>
		<table class="form-table">
		<tbody>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate page for the gateway setting page.
	 */
	public function admin_options() {
		?>
		<h2><?php esc_attr_e( 'TradeSafe', 'tradesafe-payment-gateway' ); ?></h2>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
		<?php
	}

	/**
	 * Create a transaction on TradeSafe and link it to an order.
	 *
	 * @param int $order_id WooCommerce Order Id.
	 * @return array|null
	 */
	public function process_payment( $order_id ): ?array {
		global $woocommerce;

		$client = new \TradeSafe\Helpers\TradeSafeApiClient();
		$order  = new WC_Order( $order_id );

		if ( is_null( $client ) || is_array( $client ) ) {
			return null;
		}

		if ( ! $order->meta_exists( 'tradesafe_transaction_id' ) ) {
			$user = wp_get_current_user();

			$profile = $client->profile();

			$meta_key = 'tradesafe_token_id';

			if ( tradesafe_is_prod() ) {
				$meta_key = 'tradesafe_prod_token_id';
			}

			$item_list = array();
			$vendors   = array();
			foreach ( $order->get_items() as $item ) {
				// Get product owner.
				$product = get_post( $item['product_id'] );

				if ( tradesafe_is_marketplace() && ! tradesafe_has_dokan() ) {
					if ( ! isset( $vendors[ $product->post_author ] ) ) {
						$vendors[ $product->post_author ]['total'] = 0;
					}

					$vendors[ $product->post_author ]['total'] += $item->get_total();
				}

				// Add item to list for description.
				$item_list[] = esc_attr( $item->get_name() ) . ': ' . $order->get_item_subtotal( $item );
			}

			$allocations[] = array(
				'title'         => 'Order ' . $order->get_id(),
				'description'   => implode( PHP_EOL, $item_list ),
				'value'         => ( (float) $order->get_subtotal() - (float) $order->get_discount_total() + (float) $order->get_shipping_total() + (float) $order->get_total_tax() ),
				'daysToDeliver' => 14,
				'daysToInspect' => 7,
			);

			if ( $user->ID === 0 ) {
				$token_data = $client->createToken(
					array(
						'givenName'  => $order->data['billing']['first_name'],
						'familyName' => $order->data['billing']['last_name'],
						'email'      => $order->data['billing']['email'],
						'mobile'     => $order->data['billing']['phone'],
					)
				);

				$parties[] = array(
					'role'  => 'BUYER',
					'token' => $token_data['id'],
				);
			} else {
				$parties[] = array(
					'role'  => 'BUYER',
					'token' => get_user_meta( $user->ID, $meta_key, true ),
				);
			}

			$parties[] = array(
				'role'  => 'SELLER',
				'token' => $profile['id'],
			);

			if ( tradesafe_has_dokan() ) {
				$sub_orders = get_children(
					array(
						'post_parent' => dokan_get_prop( $order, 'id' ),
						'post_type'   => 'shop_order',
						'post_status' => array(
							'wc-pending',
							'wc-completed',
							'wc-processing',
							'wc-on-hold',
							'wc-delivered',
							'wc-cancelled',
						),
					)
				);

				if ( ! $sub_orders ) {
					$payout_fee_allocation = get_option( 'tradesafe_payout_fee', 'SELLER' );

					$payout_fee = 0;

					if ( 'VENDOR' === $payout_fee_allocation ) {
						$payout_fee = 10;
					}

					$parties[] = array(
						'role'          => 'BENEFICIARY_MERCHANT',
						'token'         => get_user_meta( $order->get_meta( '_dokan_vendor_id', true ), $meta_key, true ),
						'fee'           => dokan()->commission->get_earning_by_order( $order ) - $payout_fee,
						'feeType'       => 'FLAT',
						'feeAllocation' => 'SELLER',
					);
				} else {
					$sub_order_count = count( $sub_orders );

					foreach ( $sub_orders as $sub_order_post ) {
						$sub_order = wc_get_order( $sub_order_post->ID );

						$payout_fee_allocation = get_option( 'tradesafe_payout_fee', 'SELLER' );

						$payout_fee = 0;

						if ( 'VENDOR' === $payout_fee_allocation ) {
							$payout_fee = 5 + ( 10 / $sub_order_count );
						}

						$parties[] = array(
							'role'          => 'BENEFICIARY_MERCHANT',
							'token'         => get_user_meta( $sub_order->get_meta( '_dokan_vendor_id', true ), $meta_key, true ),
							'fee'           => dokan()->commission->get_earning_by_order( $sub_order ) - $payout_fee,
							'feeType'       => 'FLAT',
							'feeAllocation' => 'SELLER',
						);
					}
				}
			} else {
				foreach ( $vendors as $vendor_id => $vendor ) {
					$fee = 0;
					if ( get_option( 'tradesafe_transaction_fee_allocation', 'SELLER' ) === 'SELLER' ) {
						switch ( get_option( 'tradesafe_transaction_fee_type' ) ) {
							case 'PERCENT':
								$fee = $vendor['total'] * ( get_option( 'tradesafe_transaction_fee' ) / 100 );
								break;
							case 'FIXED':
								$fee = get_option( 'tradesafe_transaction_fee' );
								break;
						}
					}

					$parties[] = array(
						'role'          => 'BENEFICIARY_MERCHANT',
						'token'         => get_user_meta( $vendor_id, $meta_key, true ),
						'fee'           => $vendor['total'] - $fee,
						'feeType'       => 'FLAT',
						'feeAllocation' => 'SELLER',
					);
				}
			}

			// Check all parties have a token.
			foreach ( $parties as $party ) {
				if ( null === $party['token'] || '' === $party['token'] ) {
					wc_add_notice( 'There was a problem processing your transaction. Please contact support.', $notice_type = 'error' );

					return array(
						'result'   => 'failure',
						'messages' => 'Invalid token for ' . $party['role'],
					);
				}
			}

			$transaction = $client->createTransaction(
				array(
					'title'         => 'Order ' . $order->get_id(),
					'description'   => implode( PHP_EOL, $item_list ),
					'industry'      => get_option( 'tradesafe_transaction_industry' ),
					'feeAllocation' => get_option( 'tradesafe_fee_allocation' ),
					'reference'     => $order->get_order_key() . '-' . time(),
				),
				$allocations,
				$parties
			);

			$order->add_meta_data( 'tradesafe_transaction_id', $transaction['id'], true );
			$order->save_meta_data();
			$transaction_id = $transaction['id'];
		} else {
			$transaction_id = $order->get_meta( 'tradesafe_transaction_id', true );
		}

		// Mark as pending.
		$order->update_status( 'pending', __( 'Awaiting payment.', 'tradesafe-payment-gateway' ) );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		// Return redirect.
		return array(
			'result'   => 'success',
			'redirect' => $client->getTransactionDepositLink( $transaction_id ),
		);
	}
}
