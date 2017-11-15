<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Client;
use CompropagoSdk\Tools\Validations;

class ControllerExtensionPaymentCpCash extends Controller
{
    private $errors = array();

    private $public_key;
    private $private_key;
    private $mode;
    private $status;

    public function index()
    {
        $this->load->language('extension/payment/cp_cash');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        $data = [];

        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->model_setting_setting->editSetting('payment_cp_cash', $this->request->post);

            $this->public_key = $this->request->post['payment_cp_cash_public_key'];
            $this->private_key = $this->request->post['payment_cp_cash_private_key'];
            $this->mode = $this->request->post['payment_cp_cash_mode'] == '1' ? true : false;
            $this->status = $this->request->post['payment_cp_cash_status'] == '1' ? true : false;

            $retro = $this->hook_retro(
                $this->public_key,
                $this->private_key,
                $this->mode,
                $this->status
            );

            try {
                $uri = explode("admin/index.php",$_SERVER["REQUEST_URI"]);
                $uri = $uri[0];
                $webhook_url = $this->site_url() . $uri . "index.php?route=extension/payment/cp_cash/webhook";
 
                $client = new Client($this->public_key, $this->private_key, $this->mode);
                $client->api->createWebhook($webhook_url);
            } catch(Exception $e) {
                if ($e->getMessage() != 'Error: conflict.urls.create') {
                    $retro[1] = $retro[1] == '' ? $e->getMessage() : ' - ' . $e->getMessage();
                }
            }

            $message = $this->language->get('text_success');

            if ($retro[0]) {
                $message .= ' - ' . $retro[1];
            }

            $this->session->data['success'] = $message;

            $redirect = $this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            );

            $this->response->redirect($redirect);
        }

        $this->add_warnings($data);
        $this->add_breadcrums($data);
        $this->add_data($data);

        $this->response->setOutput($this->load->view('extension/payment/cp_cash', $data));
    }

    /**
     * Return main site URL
     *
     * @return string
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function site_url() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];

        return $protocol.$domainName;
    }

    /**
     * Install module
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function install()
    {
        $this->load->model('extension/payment/cp_cash');

        $this->model_extension_payment_cp_cash->install();
    }

    /**
     * Uninstall module
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function uninstall()
    {
        $this->load->model('extension/payment/cp_cash');

        $this->model_extension_payment_cp_cash->uninstall();
    }

    /**
     * Add warnings to the admin configuration
     *
     * @param $data
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function add_warnings(&$data)
    {
        $retro = $this->hook_retro(
            $this->config->get('payment_cp_cash_public_key'),
            $this->config->get('payment_cp_cash_private_key'),
            $this->config->get('payment_cp_cash_mode') == '1' ? true : false,
            $this->config->get('payment_cp_cash_status') == '1' ? true : false
        );

        if ($retro[0]) {
            $this->errors['warning'][] = $retro[1];
        }

        if (isset($this->errors['warning'])) {
            $data['error_warning'] = '';

            foreach ($this->errors['warning'] as $value) {
                if ($data['error_warning'] == '') {
                    $data['error_warning'] = $value;
                } else {
                    $data['error_warning'] .= ' - ' . $value;
                }
            }
        }
    }

    /**
     * Add breadcrums to the navbar
     *
     * @param array $data
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com
     */
    private function add_breadcrums(&$data)
    {
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
                'extension/payment/cp_cash',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );
    }

    /**
     * Add data to the admin view
     *
     * @param array $data
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function add_data(&$data)
    {
        $data['action'] = $this->url->link(
            'extension/payment/cp_cash',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true
        );

        $client = new Client('', '', false);

        $data['status'] = $this->config->get('payment_cp_cash_status') == '1' ? true : false;
        $data['public_key'] = $this->config->get('payment_cp_cash_public_key');
        $data['private_key'] = $this->config->get('payment_cp_cash_private_key');
        $data['mode'] = $this->config->get('payment_cp_cash_mode') == '1' ? true : false;
        $data['show_logos'] = $this->config->get('payment_cp_cash_show_logos') == '1' ? true : false;
        $data['sort_order'] = $this->config->get('payment_cp_cash_sort_order');

        $providers = $this->config->get('payment_cp_cash_providers');
        $all_providers = $client->api->listDefaultProviders();

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

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
    }

    /**
     * Validates configutation errors in the module
     *
     * @param string $public_key
     * @param string $private_key
     * @param boolean $live
     * @param boolean $active
     * @return array
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    private function hook_retro($public_key, $private_key, $live, $active)
    {
        $error = array(
            false,
            '',
            'yes'
        );

        if (!$active) {
            $error[1] = 'ComproPago Payment is deactive.';
            $error[2] = 'no';
            $error[0] = true;

            return $error;
        }

        if (!empty($public_key) && !empty($private_key)) {
            try {
                $client = new Client($public_key, $private_key, $live);
                $cp_response = Validations::evalAuth($client);

                if (!Validations::validateGateway($client)) {
                    $error[1] = 'Invalid Keys, The Public Key and Private Key must be valid before using this module.';
                    $error[0] = true;
                } else if ($cp_response->mode_key != $cp_response->livemode) {
                    $error[1] = 'Your Keys and Your ComproPago account are set to different Modes.';
                    $error[0] = true;
                } else if ($live != $cp_response->livemode) {
                    $error[1] = 'Your Store and Your ComproPago account are set to different Modes.';
                    $error[0] = true;
                } else if ($live != $cp_response->mode_key) {
                    $error[1] = 'ComproPago ALERT:Your Keys are for a different Mode.';
                    $error[0] = true;
                } else if (!$cp_response->mode_key && !$cp_response->livemode) {
                    $error[1] = 'WARNING: ComproPago account is Running in TEST Mode, NO REAL OPERATIONS';
                    $error[0] = true;
                }
            } catch (Exception $e) {
                $error[2] = 'no';
                $error[1] = $e->getMessage();
                $error[0] = true;
            }
        } else {
            $error[1] = 'The Public Key and Private Key must be set before using ComproPago';
            $error[2] = 'no';
            $error[0] = true;
        }
        return $error;
    }
}