<?php
/**
 * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
 */

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Resources\Payments\Spei as sdkSpei;

class ControllerExtensionPaymentCompropagoSpei extends Controller
{
    /**
     * Render form in checkout
     * @return mixed
     */
    public function index()
    {
        $this->language->load('extension/payment/compropago_spei');

        $data['text_spei_details'] = $this->language->get('text_spei_details');

        return $this->load->view('extension/payment/compropago_spei_form', $data);
    }

    /**
     * Action to create the cash order
     */
    public function confirm()
    {
        $this->response->addHeader('Content-Type: application/json');
        $json = [
            'status'	=> null,
            'message'	=> '',
            'redirect'	=> false
        ];

        if ($this->session->data['payment_method']['code'] == 'compropago_spei') {
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder(
                $this->session->data['order_id']
            );

            try {
                $new_order = $this->create_order($order);

                $json['status']   = 'success';
                $json['redirect'] = $this->url->link(
                    'extension/payment/compropago/success&method=spei&cpid=' . $new_order['id']
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
     * Success page for cash payments
     * @return mixed
     */
    public function success()
    {
        $data['cpid'] = isset($_GET['cpid']) ? $_GET['cpid'] : '';

        $this->clear_session();
        $this->add_breadcrums($data);
        $this->add_data($data);
        $this->add_sections($data);

        $response = $this->load->view('extension/payment/compropago_cash_receipt', $data);

        return $this->response->setOutput($response);
    }

    /**
     * Add user data to success page
     * @param $data
     */
    private function add_data(&$data)
    {
        $this->language->load('extension/payment/compropago_spei');

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
    private function add_sections(&$data)
    {
        $data['continue']       = $this->url->link('common/home');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['column_right']   = $this->load->controller('common/column_right');
        $data['content_top']    = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer']         = $this->load->controller('common/footer');
        $data['header']         = $this->load->controller('common/header');
    }

    /**
     * Clear checkout session
     */
    private function clear_session()
    {
        $session_data = [
            'shipping_method',
            'shipping_methods',
            'payment_method',
            'payment_methods',
            'guest',
            'comment',
            'order_id',
            'coupon',
            'reward',
            'voucher',
            'vouchers',
            'totals'
        ];
        if (isset($this->session->data['order_id']))
        {
            $this->cart->clear();
            foreach ($session_data as $data) {
                unset($this->session->data[$data]);
            }
        }
    }

    /**
     * Add breadcrums to the success page
     * @param $data
     */
    private function add_breadcrums(&$data)
    {
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('extension/payment/compropago_spei/success')
        ];
    }

    /**
     * Create compropago order
     * @param array $order
     * @return \CompropagoSdk\Factory\Models\NewOrderInfo
     * @throws Exception
     */
    private function create_order($order)
    {
        $this->load->model('extension/payment/compropago_spei');

        $order_info = [
            "product" => [
                "id"        => "{$this->session->data['order_id']}",
                "price"     => floatval($order['total']),
                "name"      => "{$this->session->data['order_id']}",
                "url"       => "",
                "currency"  => strtoupper($order['currency_code'])
            ],
            "customer" => [
                "name"      => "{$order['payment_firstname']} {$order['payment_lastname']}",
                "email"     => "{$order['email']}",
                "phone"     => "{$order['telephone']}"
            ],
            "payment" =>  [
                "type"      => "SPEI"
            ]
        ];

        $client = (new sdkSpei)->withKeys(
            $this->config->get('payment_compropago_publickey'),
            $this->config->get('payment_compropago_privatekey')
        );
        $new_order = $client->createOrder( $order_info )['data'];
        
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
            'compropago_id'			=> $order['id'],
            'compropago_short_id'	=> $order['shortId'],
            'method'				=> 'spei'
        ];

        $data = serialize($data);

        $query = "UPDATE " . DB_PREFIX . "order
        SET compropago_data = '{$data}'
        WHERE order_id = {$this->session->data['order_id']}";

        $this->db->query($query);
    }
}
