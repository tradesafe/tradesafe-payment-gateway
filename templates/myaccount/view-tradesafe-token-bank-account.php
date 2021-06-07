<?php
/**
 * View Token Details.
 *
 * @package WooCommerce TradeSafe Gateway
 */

defined( 'ABSPATH' ) || exit;

?>
<fieldset>
	<legend><?php esc_html_e( 'Banking Details', 'woocommerce-tradesafe-gateway' ); ?> (<a href="<?php print esc_url( get_site_url() ); ?>?tradesafe=unlink">Delete</a>)
	</legend>

	<p>
		For a payout or a refund to be made to you, we require your bank account details. Please ensure you enter your
		bank account details correctly. Neither we, nor TradeSafe, will be held responsible should the funds be paid
		into another bank account should you provide incorrect bank account details.
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="tradesafe_token_bank"><?php esc_html_e( 'Bank', 'woocommerce-tradesafe-gateway' ); ?></label>
		<?php
		foreach ( $banks as $bank ) {
			if ( $bank['name'] === $token_data['bankAccount']['bank'] ?? null ) {
				echo esc_attr( $bank['description'] );
			}
		}
		?>
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-first">
		<label for="tradesafe_token_bank_account_number"><?php esc_html_e( 'Account number', 'woocommerce-tradesafe-gateway' ); ?></label>
		<?php echo esc_attr( $token_data['bankAccount']['accountNumber'] ?? null ); ?>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-last">
		<label for="tradesafe_token_bank_account_type"><?php esc_html_e( 'Account type', 'woocommerce-tradesafe-gateway' ); ?></label>
		<?php
		foreach ( $bank_account_types as $bank_account_type ) {
			if ( $bank_account_type['name'] === $token_data['bankAccount']['accountType'] ?? null ) {
				echo esc_attr( $bank_account_type['description'] );
			}
		}
		?>
	</p>
</fieldset>
