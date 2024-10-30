<?php
/**
 * Plugin Name: CentralQuality - Bancamiga Gateway for Woocommerce
 * Description: Acepta pagos a través de Bancamiga en tu tienda WooCommerce.
 *
 * Author: Filis Futsarov
 *
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Version: 1.0.0
 *
 * Requires PHP: 7.4
 *
 * Requires at least: 6.2
 * Tested up to: 6.6
 *
 * WC requires at least: 8.0
 * WC tested up to: 8.8
 *
 * Text Domain: cq-bancamiga-gateway-for-woocommerce
 * Domain Path: /languages/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CQ_Bancamiga_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'add_block_support' ) );

        // see: https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        });
	}

	public static function add_gateway( array $gateways ) {
        $gateways[] = 'CQ_Bancamiga_Payment_Gateway';
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-cq-bancamiga-payment-gateway.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin abspath.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function add_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-cq-bancamiga-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new CQ_Bancamiga_Blocks_Support() );
				}
			);
		}
	}
}

CQ_Bancamiga_Payments::init();

