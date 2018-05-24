<?php

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Client;
use CompropagoSdk\Factory\Factory;

class ControllerExtensionPaymentCompropago extends Controller {
    /**
     * Webhook to approve an order of Cash or SPEI methods
     * @throws Exception
     */
    public function webhook() {
        $this->response->addHeader('Content-Type: application/json');

        $this->load->model('extension/payment/compropago_spei');
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');

        $json = [
            'status' => 'success',
            'message' => '',
            'short_id' => null,
            'reference' => null,
        ];

        $request = @file_get_contents('php://input');

        try {
            $orderInfo = Factory::getInstanceOf('CpOrderInfo', $request);

            if (empty($request) || empty($orderInfo->id)) {
                $message = 'Invalid request';
                throw new \Exception($message);
            }

            if ($orderInfo->short_id == '000000') {
                $json ['message'] = 'OK - TEST';

                $this->response->setOutput(json_encode($json));
                return;
            }

            $order = $this->model_checkout_order->getOrder($orderInfo->order_info->order_id);

            if (empty($order)) {
                $message = 'Order not found';
                throw new \Exception($message);
            }

            $transaction = $this->getTransaction($order, $orderInfo->id);

            switch ($transaction['method']) {
                case 'spei':
                    $this->proccessSpei($json, $order, $transaction);
                    break;
                case 'cash':
                    $this->proccessCash($json, $order, $transaction);
                    break;
                default:
                    $message = "Invalid payment method {$transaction['method']}";
                    throw new \Exception($message);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            $json['status'] = 'error';
            $json['message'] = $e->getMessage();
        }

        $this->response->setOutput(json_encode($json));
        return;
    }

    /**
     * GEt transaction info of an order
     * @param array $order
     * @return array
     * @throws Exception
     */
    private function getTransaction($order, $cpid) {
        $query = "SELECT compropago_data FROM `" . DB_PREFIX . "order` WHERE order_id = {$order['order_id']}";

        $result = $this->db->query($query);

        if ($result->num_rows < 1 || empty($result->row['compropago_data'])) {
            $message = 'Can\'t find order transaction';
            throw new \Exception($message);
        }

        $transaction = unserialize($result->row['compropago_data']);

        if ($cpid != $transaction['compropago_id']) {
            $message = 'Order not found';
            throw new \Exception($message);
        }

        return $transaction;
    }

    /**
     * Proccess SPEI payment
     * @param array $json
     * @param array $order
     * @throws Exception
     */
    private function proccessSpei(&$json, $order, $transaction) {
        $verified = $this->model_extension_payment_compropago_spei->verifyOrder($transaction['compropago_id']);

        switch ($verified->status) {
            case 'PENDING':
                $status = 'charge.pending';
                break;
            case 'ACCEPTED':
                $status = 'charge.success';
                break;
            case 'EXPIRED':
                $status = 'charge.expired';
                break;
        }

        $this->updateOrderStatus($json, $status, $order, $transaction);
    }

    /**
     * Proccess Cash payment
     * @param array $json
     * @param array $order
     * @param array $transaction
     * @throws Exception
     */
    private function proccessCash(&$json, $order, $transaction) {
        $client = new Client(
            $this->config->get('payment_compropago_publickey'),
            $this->config->get('payment_compropago_privatekey'),
            $this->config->get('payment_compropago_mode') === '1'
        );

        $verified = $client->api->verifyOrder($transaction['compropago_id']);

        $this->updateOrderStatus($json, $verified->type, $order, $transaction);
    }

    /**
     * Update order status
     * @param array $json
     * @param string $status
     * @param array $order
     * @param array $transaction
     * @throws Exception
     */
    private function updateOrderStatus(&$json, $status, $order, $transaction) {
        switch ($status) {
            case 'charge.success':
                $status_id = 2;
                break;
            case 'charge.pending':
                $json['message'] = 'OK - ' . $status;
                $json['short_id'] = $transaction['compropago_short_id'];
                $json['reference'] = $order['order_id'];
                return;
            case 'charge.expired':
                $status_id = 14;
                break;
            default:
                $message = 'Invalid webhook type ' . $status;
                throw new \Exception($message);
        }

        $query = "UPDATE ". DB_PREFIX . "order SET order_status_id = ".$status_id." WHERE order_id = {$order['order_id']}";

        $this->db->query($query);

        $json['message'] = 'OK - ' . $status;
        $json['short_id'] = $transaction['compropago_short_id'];
        $json['reference'] = $order['order_id'];
        return;
    }

    /**
     * Render success page of ComproPago
     */
    public function success() {
        $this->language->load('extension/payment/compropago');

        $data['cpid'] = isset($_GET['cpid']) ? $_GET['cpid'] : '';
        $method = isset($_GET['method']) ? $_GET['method'] : '';

        $this->clear_session();
        $this->add_breadcrums($data, $method);
        $this->add_data($data);
        $this->add_sections($data);

        $response = $this->load->view('extension/payment/compropago_receipt', $data);

        return $this->response->setOutput($response);
    }

    /**
     * Clear checkout session
     */
    private function clear_session() {
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();

            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }
    }

    /**
     * Add breadcrums to the success page
     * @param $data
     */
    private function add_breadcrums(&$data, $method) {
        $checkout_link = $this->url->link(
            "extension/payment/compropago/success&method=$method&cpid={$data['cpid']}"
        );

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_success'),
            'href' => $checkout_link
        );
    }

    /**
     * Add user data to success page
     * @param $data
     */
    private function add_data(&$data) {
        $this->language->load('extension/payment/compropago');

        if ($this->customer->isLogged()) {
            $data['text_message'] = sprintf(
                $this->language->get('text_customer'),
                $this->url->link('account/account', '', true),
                $this->url->link('account/order', '', true),
                $this->url->link('account/download', '', true),
                $this->url->link('information/contact')
            );
        } else {
            $data['text_message'] = sprintf(
                $this->language->get('text_guest'),
                $this->url->link('information/contact')
            );
        }
    }

    /**
     * Add view sections
     * @param $data
     */
    private function add_sections(&$data) {
        $data['continue'] = $this->url->link('common/home');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
    }
}