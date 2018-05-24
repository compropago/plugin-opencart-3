<?php
require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Client;

class ControllerExtensionPaymentCompropagoCash extends Controller {
    private $error = array();

    /**
     * Main actions
     */
    public function index() {
        $this->load->language('extension/payment/compropago_cash');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        $data = [];

        $this->save_config();
        $this->add_warnings($data);
        $this->add_breadcrums($data);
        $this->add_buttons($data);
        $this->add_data($data);

        $this->add_sections($data);

        $this->response->setOutput($this->load->view('extension/payment/compropago_cash', $data));
    }

    /**
     * Save configurations of the panel
     */
    private function save_config() {
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_compropago', $this->request->post);
            $this->model_setting_setting->editSetting('payment_compropago_cash', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');

            $this->register_webhook(
                $this->request->post['payment_compropago_publickey'],
                $this->request->post['payment_compropago_privatekey'],
                $this->request->post['payment_compropago_mode'] === '1'
            );

            $linkParams = 'user_token=' . $this->session->data['user_token'] . '&type=payment';
            $this->response->redirect($this->url->link('marketplace/extension', $linkParams, true));
        }
    }

    /**
     * Add warnings to render
     * @param $data
     */
    private function add_warnings(&$data) {
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
    }

    /**
     * Add breadcrums to the config page
     * @param $data
     */
    private function add_breadcrums(&$data) {
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(
                'common/dashboard',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            )
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/payment/compropago_cash',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );
    }

    /**
     * Add link buttons to the page
     * @param $data
     */
    private function add_buttons(&$data) {
        $data['action'] = $this->url->link(
            'extension/payment/compropago_cash',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true
        );
    }

    /**
     * Add page sections to render in view
     * @param $data
     */
    private function add_sections(&$data) {
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
    }

    /**
     * Add the config data saved to render in view
     * @param $data
     */
    private function add_data(&$data) {
        if (isset($this->request->post['payment_compropago_mode'])) {
            $data['payment_compropago_mode'] = $this->request->post['payment_compropago_mode'];
        } else {
            $data['payment_compropago_mode'] = $this->config->get('payment_compropago_mode');
        }

        if (isset($this->request->post['payment_compropago_publickey'])) {
            $data['payment_compropago_publickey'] = $this->request->post['payment_compropago_publickey'];
        } else {
            $data['payment_compropago_publickey'] = $this->config->get('payment_compropago_publickey');
        }

        if (isset($this->request->post['payment_compropago_privatekey'])) {
            $data['payment_compropago_privatekey'] = $this->request->post['payment_compropago_privatekey'];
        } else {
            $data['payment_compropago_privatekey'] = $this->config->get('payment_compropago_privatekey');
        }

        if (isset($this->request->post['payment_compropago_cash_status'])) {
            $data['payment_compropago_cash_status'] = $this->request->post['payment_compropago_cash_status'];
        } else {
            $data['payment_compropago_cash_status'] = $this->config->get('payment_compropago_cash_status');
        }

        if (isset($this->request->post['payment_compropago_cash_title'])) {
            $data['payment_compropago_cash_title'] = $this->request->post['payment_compropago_cash_title'];
        } else {
            $data['payment_compropago_cash_title'] = $this->config->get('payment_compropago_cash_title');
        }

        if (isset($this->request->post['payment_compropago_cash_sort_order'])) {
            $data['payment_compropago_cash_sort_order'] = $this->request->post['payment_compropago_cash_sort_order'];
        } else {
            $data['payment_compropago_cash_sort_order'] = $this->config->get('payment_compropago_cash_sort_order');
        }

        if (isset($this->request->post['payment_compropago_cash_providers'])) {
            $providers = $this->request->post['payment_compropago_cash_providers'];
        } else {
            $providers = $this->config->get('payment_compropago_cash_providers');
        }

        $client = new Client(
            $data['payment_compropago_publickey'],
            $data['payment_compropago_privatekey'],
            $data['payment_compropago_mode'] === '1'
        );

        $data['payment_compropago_cash_providers'] = $providers;

        try {
            $all_providers = $client->api->listDefaultProviders();
        } catch (Exception $e) {
            $all_providers = [];
        }

        if (empty($providers)) {
            $data['active_providers'] = [];
            $data['deactive_providers'] = $all_providers;
        } else {
            $active = explode(',', $providers);

            foreach ($all_providers as $provider) {
                if (in_array($provider->internal_name, $active)) {
                    $data['active_providers'][] = $provider;
                } else {
                    $data['deactive_providers'][] = $provider;
                }
            }
        }
    }

    /**
     * Validate if the usar has access to mody the plugin
     * @return bool
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/compropago_cash')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Register webhook in ComproPago
     * @param $public_key
     * @param $private_key
     * @param $mode
     */
    private function register_webhook($public_key, $private_key, $mode) {
        try {
            $uri = explode("admin/index.php",$_SERVER["REQUEST_URI"]);
            $uri = $uri[0];
            $webhook_url = $this->site_url() . $uri . "index.php?route=extension/payment/cp_cash/webhook";

            $client = new Client($public_key, $private_key, $mode);
            $client->api->createWebhook($webhook_url);
        } catch(Exception $e) {
            if ($e->getMessage() != 'Error: 409') {
                $this->error['warning'] = 'Webhook error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Install secuence
     */
    public function install() {
        $this->load->model('extension/payment/compropago_cash');
        $this->model_extension_payment_compropago_cash->install();
    }

    /**
     * Get the base path url of the site
     * @return string
     */
    private function site_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        return $protocol.$domainName;
    }
}