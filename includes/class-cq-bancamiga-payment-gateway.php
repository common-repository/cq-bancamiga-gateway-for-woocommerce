<?php
/**
 * @author   Filis Futsarov <filisfutsarov@gmail.com>
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CentralQuality Bancamiga Gateway.
 *
 * @class    CQ_Bancamiga_Payment_Gateway
 * @version  1.0.0
 */
class CQ_Bancamiga_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Whether the test mode is enabled.
     *
     * @var bool
     */
    public $testmode;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $checkout_expiration_in_minutes;

    /**
     * Prefix for options table.
     *
     * @var string
     */
    public $plugin_id = 'centralquality_';

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'bancamiga';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		
		$this->has_fields         = false;
		$this->supports           = array(
			'products'
		);

		$this->method_title       = _x( 'Bancamiga', 'Bancamiga payment method', 'cq-bancamiga-gateway-for-woocommerce' );
		$this->method_description = __( 'Allows Bancamiga payments.', 'cq-bancamiga-gateway-for-woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
        $this->testmode                 = 'yes' === $this->get_option('testmode', 'yes');
        $this->token                    = $this->get_option('token');
        $this->checkout_expiration_in_minutes = $this->get_option('checkout_expiration_in_minutes', 10);

        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array( $this, 'process_admin_options' )
        );

        add_action(
            'woocommerce_api_'.$this->id,
            array( $this, 'successfull_callback' ),
        );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
            'enabled'         => array(
                'title'   => __( 'Habilitar/Deshabilitar', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Habilitar Bancamiga', 'cq-bancamiga-gateway-for-woocommerce' ),
                'default' => 'no',
            ),
            'testmode'         => array(
                'title'   => __( 'Modo prueba', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Habilitar sandbox', 'cq-bancamiga-gateway-for-woocommerce' ),
                'default' => 'yes',
            ),
            'token'           => array(
                'title'       => __( 'Token de producción', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'        => 'safe_text',
            ),
            'checkout_expiration_in_minutes'         => array(
                'title'   => __( 'Tiempo de expiración del enlace de pago (en minutos)', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'    => 'number',
                'default' => 10,
            ),
            'title'           => array(
                'title'       => __( 'Título', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'        => 'safe_text',
                'description' => __( 'El título que ve el usuario a la hora de elegir un método de pago.', 'cq-bancamiga-gateway-for-woocommerce' ),
                'default'     => 'Bancamiga',
                'desc_tip'    => true,
            ),
            'description'     => array(
                'title'       => __( 'Descripción', 'cq-bancamiga-gateway-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'La descripción del método de pago que el usuario ve a la hora de seleccionarlo.', 'cq-bancamiga-gateway-for-woocommerce' ),
                'default'     => __( 'Hacer el pago a través de Bancamiga.', 'cq-bancamiga-gateway-for-woocommerce' ),
                'desc_tip'    => true,
            ),
		);
	}

    /**
     * @return string
     */
    private function get_url($url)
    {
        $baseUrl = 'https://payments3ds.bancamiga.com/';

        if ($this->testmode) {
            $baseUrl .= 'sandbox/';
        }

        return $baseUrl.$url;
    }

    /**
     * @return string
     */
    private function get_token()
    {
        if ($this->testmode) {
            return 'sandbox_7625372:1';
        }

        return $this->get_option( 'token' );
    }

    private function get_callback_url(string $params)
    {
        if ('' == get_option('permalink_structure')){
            $callback_prefix = sprintf('%s/?wc-api=%s&%s', site_url(), $this->id, $params);
        } else {
            $callback_prefix = sprintf('%s/wc-api/%s/?%s', site_url(), $this->id, $params);
        }

        return $callback_prefix;
    }

    public function process_payment($order_id)
    {
        // remove Cart, as Order is already placed by now
        WC()->cart->empty_cart();

        $logger = wc_get_logger();

        $order = wc_get_order($order_id);

        if ( $order->get_total() == 0 ) {
            $order->payment_complete();
            // Return thank you page redirect.
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        $response = (new WP_Http)->request(
            $this->get_url('init/cdd69752-1dd2-48d7-b2f0-f3b15cb2476b'),
            [
                'method' => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer '.$this->get_token(),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => [
                    'Descripcion' => apply_filters( 'cq_bancamiga_checkout_description', sprintf('Pago para %s', get_bloginfo('name')), $order),
                    'Name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    // store woocommerce order ID just in case?
                    'Externalid' => $order_id,
                    'Monto' => apply_filters( 'cq_bancamiga_checkout_amount', $order->get_total(), $order),
                    'Urldone' => $this->get_callback_url('bancamiga_order_id={{OrderID}}'),
                    'Urlcancel' => $this->get_callback_url('bancamiga_order_id={{OrderID}}&canceled=1'),
                    'Expireminute' => $this->checkout_expiration_in_minutes
                ]
            ]
        );

        if (is_wp_error($response)) {
            wc_add_notice( __('Error interno al generar el enlace de pago de Bancamiga. Contacta con el administrador.', 'cq-bancamiga-gateway-for-woocommerce'), 'error');

            $order->update_status(
                'failed',
                __( 'Fallo al generar el enlace de pago del pedido debido a un error en la aplicación.', 'cq-bancamiga-gateway-for-woocommerce' )
            );

            $logger->critical(sprintf('Bancamiga API payment link request error: "%s"', $response->get_error_message()), [
                'messages' => $response->get_error_messages(),
                'code' => $response->get_error_codes()
            ]);

            return;
        }

        $body = json_decode($response['body'], true);

        $api_status = $body['status'];
        if ($api_status !== 200) {
            wc_add_notice( __('Error del proveedor de pagos Bancamiga', 'cq-bancamiga-gateway-for-woocommerce'), 'error');

            $order->update_status(
                'failed',
                __( 'Fallo al generar el enlace de pago del pedido debido a un error en Bancamiga.', 'cq-bancamiga-gateway-for-woocommerce' )
            );

            $logger->critical(
                sprintf('Bancamiga API response error: "%s"', $body['mensaje']),
                ['api' => $body]
            );

            return;
        }

        $payment_link = $body['data']['url'];
        $bancamiga_order_id = $body['data']['ordenID'];

        $order->add_order_note(__( 'Se ha generado un enlace de pago de Bancamiga y se procede a redirigir al cliente.', 'cq-bancamiga-gateway-for-woocommerce'));
        // for cross-reference purposes later on callback
        $order->add_meta_data( '_bancamiga_order_id', $bancamiga_order_id, true );
        $order->save_meta_data();

        $logger->info(
            sprintf(
                /* translators: 1: WooCommerce Order ID 2: Bancamiga Order ID 3: minutes*/
                __('Pedido #%1$s. Se ha generado un enlace de pago con ID "%2$s" que expira en %3$d minutos.', 'cq-bancamiga-gateway-for-woocommerce'),
                $order->get_id(),
                $bancamiga_order_id,
                $this->checkout_expiration_in_minutes
            )
        );

        return [
            'result' => 'success',
            'redirect' => $payment_link
        ];
    }

    /**
     * @return void
     */
    public function successfull_callback()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $bancamiga_order_id = isset($_GET['bancamiga_order_id']) ? wc_clean(wp_unslash( $_GET['bancamiga_order_id'])) : null;

        if (!$bancamiga_order_id) {
            wp_redirect(site_url());
            return;
        }

        // retrieve Order with Bancamiga ID previously stored on order's metadata
        $order = wc_get_orders([
            'meta_key' => '_bancamiga_order_id',
            'meta_value' => $bancamiga_order_id,
            'meta_compare' => '='
        ])[0] ?? null;
        if (!$order) {
            /* translators: Bancamiga Order ID */
            wc_add_notice(sprintf(__('No hay un pedido asociado a este pago de Bancamiga: %s', 'cq-bancamiga-gateway-for-woocommerce'), $bancamiga_order_id), 'error');
            wp_redirect(wc_get_checkout_url());
            return;
        }

        if ($order->is_paid()) {
            wc_add_notice(__('Este pedido ya fue pagado.', 'cq-bancamiga-gateway-for-woocommerce'));
            wp_redirect($order->get_view_order_url());
            return;
        }

        $logger = wc_get_logger();

        $response = (new WP_Http)->request(
            $this->get_url('orden/'.$bancamiga_order_id),
            [
                'method' => 'GET',
                'headers' => [
                    'Authorization' => 'Bearer '.$this->get_token(),
                ]
            ]
        );

        if (is_wp_error($response)) {
            /* translators: WooCommerce Order ID */
            wc_add_notice(sprintf(__('Error interno al verificar el pago del pedido #%s', 'cq-bancamiga-gateway-for-woocommerce'), (string)$order->get_id()), 'error');

            $order->update_status(
                'failed',
                __( 'Fallo al confirmar el pago del pedido debido a un error en la aplicación.', 'cq-bancamiga-gateway-for-woocommerce' )
            );

            $logger->critical(sprintf('Callback request error: "%s"', $response->get_error_message()), [
                'messages' => $response->get_error_messages(),
                'code' => $response->get_error_codes()
            ]);

            wp_redirect($order->get_checkout_payment_url());
            return;
        }

        $body = json_decode($response['body'], true);

        $api_status = $body['status'];
        // pending and canceled payments (expiration timeout, card rejected, etc.) fall here
        if ($api_status !== 200) {
            wc_add_notice(
                sprintf(
                    /* translators: WooCommerce Order ID */
                    __('Fallo al confirmar el pago del pedido #%s', 'cq-bancamiga-gateway-for-woocommerce'),
                    (string)$order->get_id()
                ),
            'error');

            $order->update_status(
                'failed',
                __( 'Fallo al confirmar el pago del pedido debido a un error en Bancamiga.', 'cq-bancamiga-gateway-for-woocommerce' )
            );

            $logger->critical(
                sprintf('Callback response error: "%s"', $body['mensaje']),
                ['api' => $body]
            );

            wp_redirect($order->get_checkout_payment_url());
            return;
        }

        $payment_status = $body['data']['Status'];
        if ($payment_status !== 'approved') {
            $known_bancamiga_statuses = [
                'pending' => __('Pendiente', 'cq-bancamiga-gateway-for-woocommerce'),
                'rejected' => __('Rechazado', 'cq-bancamiga-gateway-for-woocommerce'),
                'approved' => __('Aprobado', 'cq-bancamiga-gateway-for-woocommerce'),
            ];

            $report_status = $known_bancamiga_statuses[$payment_status] ?? $payment_status;

            $order->update_status(
                'failed',
                /* translators: Bancamiga payment status */
                sprintf(__( 'Fallo al confirmar el pago del pedido. El estado del pago en Bancamiga es %s.', 'cq-bancamiga-gateway-for-woocommerce' ), $report_status),
            );

            wc_add_notice(
                sprintf(
                    /* translators: 1: WooCommerce Order ID 2: Bancamiga payment status*/
                    __('Fallo al confirmar el pago del pedido #%1$s. El estado del pago en Bancamiga es %2$s. Inténtelo de nuevo.', 'cq-bancamiga-gateway-for-woocommerce'),
                    (string)$order->get_id(), $report_status
                ),
                'error'
            );

            $logger->notice(sprintf(
                /* translators: 1: WooCommerce Order ID 2: Bancamiga Order ID*/
                __('Pedido #%1$s. Fallo al intentar confirmar el pago "%2$s".', 'cq-bancamiga-gateway-for-woocommerce'), $order->get_id(), $bancamiga_order_id),
                [
                    'data' => self::remove_personal_data($body['data'])
                ]
            );

            wp_redirect($order->get_checkout_payment_url());
            return;
        }

        $payment_transaction_id = $body['data']['CodigoTransaccion'];

        /* translators: Bancamiga Transaction ID */
        $message = sprintf(__('El estado del pago fue aprobado en Bancamiga (ID de Transacción: %s)', 'cq-bancamiga-gateway-for-woocommerce'), $payment_transaction_id);
        $order->add_order_note( $message );

        $order->payment_complete($payment_transaction_id);

        /* translators: WooCommerce Order ID */
        $logger->info(sprintf(__('Pedido #%s. Se ha confirmado el pago.', 'cq-bancamiga-gateway-for-woocommerce'), $order->get_id()));

        wp_redirect($this->get_return_url($order));
    }

    private static function remove_personal_data(array $response_data): array
    {
        unset($response_data['Name']);
        unset($response_data['Dni']); // always empty but just in case

        return $response_data;
    }
}
