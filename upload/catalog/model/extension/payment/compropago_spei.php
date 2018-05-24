<?php
require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Tools\Request;

class ModelExtensionPaymentCompropagoSpei extends Model {
    /**
     * Render Payment option in checkout
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total) {
        $this->language->load('extension/payment/compropago_spei');

        $title = '<img src="https://compropago.com/plugins/compropago-spei-v2.svg" style="height: 20px;">';
        $title .= ' - ' . $this->config->get('payment_compropago_spei_title');

        return array(
            'code'       => 'compropago_spei',
            'title'      => $title,
            'terms'      => '',
            'sort_order' => $this->config->get('payment_compropago_spei_sort_order')
        );
    }

    /**
     * Create SPEI order
     * @param array $orderInfo
     * @return object
     * @throws Exception
     */
    public function createOrder($orderInfo) {
        $this->load->model('setting/setting');

        $auth = [
            'user' => $this->config->get('payment_compropago_privatekey'),
            'pass' => $this->config->get('payment_compropago_publickey')
        ];

        $url = 'https://api.compropago.com/v2/orders';
        $response = Request::post($url, $orderInfo, array(), $auth);

        if ($response->statusCode != 200) {
            throw new \Exception("SPEI Error #: {$response->statusCode}");
        }
        $body = json_decode($response->body);

        return $body->data;
    }

    /**
     * Verify the status of a SPEI payment
     * @param string $orderId
     * @return object
     * @throws Exception
     */
    public function verifyOrder($orderId) {
        $this->load->model('setting/setting');

        $auth = [
            'user' => $this->config->get('payment_compropago_privatekey'),
            'pass' => $this->config->get('payment_compropago_publickey')
        ];

        $url = 'https://api.compropago.com/v2/orders/' . $orderId;

        $response = Request::get($url, array(), $auth);

        if ($response->statusCode != 200) {
            $message = "Can't verify order";
            throw new \Exception($message);
        }

        $body = json_decode($response->body);

        return $body->data;
    }
}