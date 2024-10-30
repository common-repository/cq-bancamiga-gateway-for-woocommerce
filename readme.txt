=== CentralQuality - Bancamiga Gateway for Woocommerce ===
Contributors: filisko
Tags: bancamiga, woocommerce, payment gateway, payment method
Requires at least: 6.2
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Acepta pagos en línea a través de Bancamiga para tu Tienda WooCommerce.

== Description ==

Acepta pagos en línea a través de Bancamiga para tu Tienda WooCommerce.

- **Modo de prueba (sandbox) por defecto**.
- **Transparente**. Se guarda todo el proceso de pago tanto en los logs de WooCommerce como en las Notas del pedido. Desde la generación del enlace de pago hasta su confirmación.
- **Partes modificables a través de Hooks**.
    - **cq_bancamiga_checkout_amount**: Permite modificar el importe del pago.
    ```php
    add_filter('cq_bancamiga_checkout_amount', function (string $default, WC_Order $order) {
        return $order->get_total() + 5;
    }, 10, 2);
    ```
    - **cq_bancamiga_checkout_description**: Permite modificar el texto que aparece en Bancamiga como descripción de lo que se está comprando.
    ```php
    add_filter('cq_bancamiga_checkout_description', function (string $default, WC_Order $order) {
        return sprintf('Pago para %s', get_bloginfo('name'));
    }, 10, 2);
    ```
- 100% probado con pruebas automatizadas (tests de integración y end to end).
