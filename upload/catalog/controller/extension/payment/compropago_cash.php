<?php
/**
 * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
 */

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Resources\Payments\Cash as sdkCash;


class ControllerExtensionPaymentCompropagoCash extends Controller
{
    /**
     * Render form in checkout
     * @return mixed
     */
    public function index()
    {
        $this->language->load('extension/payment/compropago_cash');
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        try {
            $client = (new sdkCash)->withKeys(
                $this->config->get('payment_compropago_publickey'),
                $this->config->get('payment_compropago_privatekey')
            );
            $all_providers = $client->getProviders(
                floatval($order_info['total']),
                $order_info['currency_code']
            );
        } catch (Exception $e) {
            $all_providers = [];
        }

        $data['providers'] = [];
        $allow_providers = explode(',', $this->config->get('payment_compropago_cash_providers'));
        foreach ($all_providers as $provider) {
            if (in_array($provider['internal_name'], $allow_providers)) {
                $data['providers'][] = $provider;
            }
        }

        return $this->load->view('extension/payment/compropago_cash_form', $data);
    }

    /**
     * Action to create the cash order
     */
    public function confirm()
    {
        $this->response->addHeader('Content-Type: application/json');
        $json = [
            'status'    => null,
            'message'   => '',
            'redirect'  => false
        ];

        if ($this->session->data['payment_method']['code'] == 'compropago_cash') {
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder(
                $this->session->data['order_id']
            );

            try {
                $new_order = $this->create_order($order);

                $json['status']   = 'success';
                $json['redirect'] = $this->url->link(
                    'extension/payment/compropago/success&method=cash&cpid=' . $new_order['id']
                );
            } catch (Exception $e) {
                $json['status']   = 'error';
                $json['message']  = $e->getMessage();
            }
        }

        $this->response->setOutput(json_encode($json));
        return;
    }

    /**
     * Create compropago order
     * @param array $order
     * @return \CompropagoSdk\Factory\Models\NewOrderInfo
     * @throws Exception
     */
    private function create_order($order)
    {
        $order_info = [
            'order_id'              => "{$this->session->data['order_id']}",
            'order_name'            => "{$this->session->data['order_id']}",
            'order_price'           => floatval($order['total']),
            'customer_name'         => "{$order['payment_firstname']} {$order['payment_lastname']}",
            'customer_email'        => "{$order['email']}",
            'customer_phone'        => "{$order['telephone']}",
            'currency'              => strtoupper($order['currency_code']),
            'payment_type'          => $this->request->post['provider'],
            'app_client_name'       => 'opencart',
            'app_client_version'    => VERSION
        ];

        $client = (new sdkCash)->withKeys(
            $this->config->get('payment_compropago_publickey'),
            $this->config->get('payment_compropago_privatekey')
        );
        $new_order = $client->createOrder( $order_info );

        $this->add_transaction($new_order);

        return $new_order;
    }

    /**
     * Add transaction information to the order
     * @param $order
     */
    private function add_transaction($order)
    {
        $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 1);

        $data = [
            'compropago_id'         => $order['id'],
            'compropago_short_id'   => $order['short_id'],
            'method'                => 'cash'
        ];

        $data = serialize($data);

        $query = "UPDATE " . DB_PREFIX . "order
        SET compropago_data = '{$data}'
        WHERE order_id = {$order['order_info']['order_id']}";

        $this->db->query($query);
    }
}
