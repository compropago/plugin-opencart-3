<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Client;
use CompropagoSdk\Factory\Factory;

class ControllerExtensionPaymentCpCash extends Controller
{
    /**
     * Render provider selection view
     *
     * @return mixed
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function index() {
        $this->language->load('extension/payment/cp_cash');
        $this->load->model('checkout/order');

        $data['text_title']         = $this->language->get('text_title');
        $data['button_confirm']     = $this->language->get('button_confirm');

        $data['show_logos'] = $this->config->get('payment_cp_cash_show_logos');

        $order_info   = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $client = new Client(
            $this->config->get('payment_cp_cash_public_key'),
            $this->config->get('payment_cp_cash_private_key'),
            $this->config->get('payment_cp_cash_mode') == '1' ? true : false
        );
        
        $allow_providers = explode(',', $this->config->get('payment_cp_cash_providers'));
        $all_providers = $client->api->listProviders(floatval($order_info['total']), $order_info['currency_code']);

        $data['providers'] = [];

        foreach ($all_providers as $provider){
            if (in_array($provider->internal_name, $allow_providers)){
                $data['providers'][] = $provider;
            }
        }

        return $this->load->view('extension/payment/cp_cash_providers', $data);
    }

    /**
     * Generate order in compropago and return a json with the redirect url to the receipt
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function confirm() {
        $json = array();

        if ($this->session->data['payment_method']['code'] == 'cp_cash') {
            $this->load->model('checkout/order');
            $client = new Client(
                $this->config->get('payment_cp_cash_public_key'),
                $this->config->get('payment_cp_cash_private_key'),
                $this->config->get('payment_cp_cash_mode') == '1' ? true : false
            );
            
            $order_id = $this->session->data['order_id'];
            $order_info = $this->model_checkout_order->getOrder($order_id);
            $products = $this->cart->getProducts();

            $order_name = '';
            foreach ($products as $product) {
                $order_name .= $product['name'];
            }

            $data_order = [
                'order_id' => $order_id,
                'order_name' => $order_name,
                'order_price' => floatval($order_info['total']),
                'customer_name' => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'],
                'customer_email' => $order_info['email'],
                'currency' => $order_info['currency_code'],
                'image_url' => null,
                'payment_type' => $this->request->post['payment_type'],
                'app_client_name' => 'opencart',
                'app_client_version' => VERSION
            ];
            
            try {
                $ordercp = Factory::getInstanceOf('PlaceOrderInfo', $data_order);
                
                $response = $client->api->placeOrder($ordercp) ;
                
                $recordTime = time();
                $order_id = $order_info['order_id'];
                $ioIn = base64_encode(json_encode($response));
                $ioOut = base64_encode(json_encode($data_order));

                // $query = "INSERT INTO " . DB_PREFIX . "compropago_orders 
                //     (`date`,`modified`,`compropagoId`,`compropagoStatus`,`storeCartId`,`storeOrderId`,`storeExtra`,`ioIn`,`ioOut`)".
                //     " values (:fecha:,:modified:,':cpid:',':cpstat:',':stcid:',':stoid:',':ste:',':ioin:',':ioout:')";

                // $query = str_replace(":fecha:", $recordTime, $query);
                // $query = str_replace(":modified:", $recordTime, $query);
                // $query = str_replace(":cpid:", $response->id, $query);
                // $query = str_replace(":cpstat:", $response->type, $query);
                // $query = str_replace(":stcid:", $order_id, $query);
                // $query = str_replace(":stoid:", $order_id, $query);
                // $query = str_replace(":ste:", $response->type, $query);
                // $query = str_replace(":ioin:", $ioIn, $query);
                // $query = str_replace(":ioout:", $ioOut, $query);

                // $this->db->query($query);

                // $compropagoOrderId = $this->db->getLastId();
                // $query2 = "INSERT INTO ".DB_PREFIX."compropago_transactions
                //     (orderId,date,compropagoId,compropagoStatus,compropagoStatusLast,ioIn,ioOut)
                //     values (:orderid:,:fecha:,':cpid:',':cpstat:',':cpstatl:',':ioin:',':ioout:')";

                // $query2 = str_replace(":orderid:", $compropagoOrderId, $query2);
                // $query2 = str_replace(":fecha:", $recordTime, $query2);
                // $query2 = str_replace(":cpid:", $response->id, $query2);
                // $query2 = str_replace(":cpstat:", $response->type, $query2);
                // $query2 = str_replace(":cpstatl:", $response->type, $query2);
                // $query2 = str_replace(":ioin:", $ioIn, $query2);
                // $query2 = str_replace(":ioout:", $ioOut, $query2);

                // $this->db->query($query2);

                $query_update = "UPDATE ".DB_PREFIX."order SET order_status_id = 1 WHERE order_id = $order_id";
                $this->db->query($query_update);

                $json = [
                    'status' => 'success',
                    'redirect' => htmlspecialchars_decode($this->url->link('extension/payment/cp_cash/success', 'order_id=' . $response->id , 'SSL')),
                    'order_history' => $this->config->get('payment_cod_order_status_id')
                ];

                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 1);
            } catch(Exception $e) {
                $json = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }

        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Display ComproPago receipt
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function success() {
        $this->language->load('extension/payment/cp_cash');
        $this->cart->clear();

        $data['order_id'] = $this->request->get['order_id'];
        $this->addBreadcrums($data);
        $this->addData($data);

        //$this->response->redirect($this->url->link('checkout/success', '', true));
        $this->response->setOutput($this->load->view('extension/payment/cp_cash_receipt', $data));
    }

    public function webhook(){
        $this->response->addHeader('Content-Type: application/json');

        $request = @file_get_contents('php://input');

        if(!$resp_webhook = Factory::getInstanceOf('CpOrderInfo', $request)){
            $this->response->setOutput(json_encode([
                'status' => 'error',
                'message' => 'invalid request',
                'short_id' => null,
                'reference' => null
            ]));
            return;
        }

        $publickey = $this->config->get('payment_cp_cash_public_key');
        $privatekey = $this->config->get('payment_cp_cash_private_key');
        $live = $this->config->get('cppayment_mode') == '1' ? true : false;

        try{
            $client = new Client($publickey, $privatekey, $live);

            if($resp_webhook->id == "ch_00000-000-0000-000000"){
                $this->response->setOutput(json_encode([
                    'status' => 'success',
                    'message' => 'OK - test',
                    'short_id' => $resp_webhook->id,
                    'reference' => $resp_webhook->order_info->order_id
                ]));
                return;
            }

            $this_order = $this->db->query("SELECT * FROM ". DB_PREFIX ."compropago_orders WHERE compropagoId = '".$resp_webhook->id."'");

            if($this_order->num_rows == 0){
                $this->response->setOutput(json_encode([
                    'status' => 'error',
                    'message' => 'Order not found in store',
                    'short_id' => null,
                    'reference' => null
                ]));
                return;
            }

            $id = intval($this_order->row['storeOrderId']);
            $response = $client->api->verifyOrder($resp_webhook->id);

            switch ($response->type){
                case 'charge.success':
                    $status_id = 2;
                    break;
                case 'charge.pending':
                    $this->response->setOutput(json_encode([
                        'status' => 'success',
                        'message' => 'OK - ' . $response->type,
                        'short_id' => $response->id,
                        'reference' => $response->order_info->order_id
                    ]));
                    return;
                case 'charge.expired':
                    $status_id = 14;
                    break;
                default:
                    $this->response->setOutput(json_encode([
                        'status' => 'error',
                        'message' => 'invalid webhook type',
                        'short_id' => $response->id,
                        'reference' => $response->order_info->order_id
                    ]));
                    return;
            }

            $this->db->query("UPDATE ". DB_PREFIX . "order SET order_status_id = ".$status_id." WHERE order_id = ".$id);

            $recordTime = time();
            $query = "UPDATE ". DB_PREFIX ."compropago_orders SET
                modified = ".$recordTime.",
                compropagoStatus = '".$response->status."',
                storeExtra = '".$response->status."'
                WHERE id = ".$id;

            $this->db->query($query);
            $ioIn = base64_encode(json_encode($request));
            $ioOut = base64_encode(json_encode($response));

            $query2 = "INSERT INTO ".DB_PREFIX."compropago_transactions
                (orderId,date,compropagoId,compropagoStatus,compropagoStatusLast,ioIn,ioOut)
                values (:orderid:,:fecha:,':cpid:',':cpstat:',':cpstatl:',':ioin:',':ioout:')";

            $query2 = str_replace(":orderid:", $this_order->row['id'], $query2);
            $query2 = str_replace(":fecha:", $recordTime, $query2);
            $query2 = str_replace(":cpid:", $response->id, $query2);
            $query2 = str_replace(":cpstat:", $response->status, $query2);
            $query2 = str_replace(":cpstatl:", $this_order->row['compropagoStatus'], $query2);
            $query2 = str_replace(":ioin:", $ioIn, $query2);
            $query2 = str_replace(":ioout:", $ioOut, $query2);

            $this->db->query($query2);

            $this->response->setOutput(json_encode([
                'status' => 'success',
                'message' => 'OK - ' . $response->type,
                'short_id' => $resp_webhook->id,
                'reference' => $resp_webhook->order_info->order_id
            ]));
            return;
        }catch (Exception $e) {
            $this->response->setOutput(json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'short_id' => null,
                'reference' => null
            ]));
            return;
        }
    }

    /**
     * Add breadcrums data
     *
     * @param array $data
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function addBreadcrums(&$data) {
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
            'href' => $this->url->link('checkout/checkout', '', 'SSL')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success')
        );
    }

    /**
     * Add secuencial data for reder view
     *
     * @param array $data
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function addData(&$data) {
        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue'] = $this->url->link('common/home');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
    }
}