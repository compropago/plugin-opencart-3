<?php

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';

use CompropagoSdk\Resources\Payments\Cash as sdkCash;
use CompropagoSdk\Resources\Webhook;


class ControllerExtensionPaymentCompropagoCash extends Controller
{
	private $error			= [];
	private $gateway_name	= 'compropago_cash';

	/**
	 * Main actions
	 */
	public function index()
	{
		$this->load->language("extension/payment/{$this->gateway_name}");
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		$data = [];

		$this->save_config();
		$this->add_warnings($data);
		$this->add_breadcrums($data);
		$this->add_buttons($data);
		$this->add_data($data);
		$this->add_sections($data);

		$this->response->setOutput($this->load->view(
			"extension/payment/{$this->gateway_name}",
			$data
		));
	}

	/**
	 * Save configurations of the panel
	 */
	private function save_config()
	{
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate())
		{
			$this->model_setting_setting->editSetting('payment_compropago', $this->request->post);
			$this->model_setting_setting->editSetting("payment_{$this->gateway_name}", $this->request->post);
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
	private function add_warnings(&$data)
	{
		$data['error_warning'] = isset($this->error['warning'])
			? $this->error['warning']
			: '';
	}

	/**
	 * Add breadcrums to the config page
	 * @param $data
	 */
	private function add_breadcrums(&$data)
	{
		$data['breadcrumbs'] = [];

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
				"extension/payment/{$this->gateway_name}",
				'user_token=' . $this->session->data['user_token'],
				true
			)
		);
	}

	/**
	 * Add link buttons to the page
	 * @param $data
	 */
	private function add_buttons(&$data)
	{
		$data['action'] = $this->url->link(
			"extension/payment/{$this->gateway_name}",
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
	private function add_sections(&$data)
	{
		$data['header']			= $this->load->controller('common/header');
		$data['column_left']	= $this->load->controller('common/column_left');
		$data['footer']			= $this->load->controller('common/footer');
	}

	/**
	 * Add the config data saved to render in view
	 * @param $data
	 */
	private function add_data(&$data)
	{
		$params = [
			'payment_compropago_mode',
			'payment_compropago_publickey',
			'payment_compropago_privatekey',
			'payment_compropago_cash_status',
			'payment_compropago_cash_title',
			'payment_compropago_cash_sort_order',
			'payment_compropago_cash_providers'
		];
		foreach($params as $param)
		{
			$data[$param] = isset($this->request->post[$param])
				? $this->request->post[$param]
				: $this->config->get($param);
		}

		try
		{
			$client = (new sdkCash)->withKeys(
				$data['payment_compropago_publickey'],
				$data['payment_compropago_privatekey']
			);
			$all_providers = $client->getDefaultProviders();
		}
		catch (Exception $e)
		{
			$all_providers = [];
		}

		if (empty($providers))
		{
			$data['active_providers'] = [];
			$data['deactive_providers'] = $all_providers;
		}
		else
		{
			$active = explode(',', $providers);
			foreach ($all_providers as $provider)
			{
				if (in_array($provider->internal_name, $active))
				{
					$data['active_providers'][] = $provider;
				}
				else
				{
					$data['deactive_providers'][] = $provider;
				}
			}
		}
	}

	/**
	 * Validate if the usar has access to mody the plugin
	 * @return bool
	 */
	protected function validate()
	{
		if (!$this->user->hasPermission('modify', "extension/payment/{$this->gateway_name}"))
		{
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
	private function register_webhook($public_key, $private_key, $mode)
	{
		try
		{
			$uri = explode("admin/index.php", $_SERVER["REQUEST_URI"]);
			$uri = $uri[0];
			$webhook_url = $this->site_url() . "{$uri}index.php?route=extension/payment/compropago/webhook";

			$client = (new Webhook)->withKeys(
				$public_key,
				$private_key
			);
			$response = $client->create( $webhook_url );
		}
		catch(Exception $e)
		{
			$errors = [
				'Request Error [409]: ',
			];
			$response = json_decode(str_replace($errors, '', $e->getMessage()), true);

			# Ignore Webhook registered
			if ( isset($response['code']) && $response['code']==409 )
			{
			}
			else
			{
				$this->error['warning'] = isset($response['message'])
					? $response['message']
					: 'Webhook error: ' . $e->getMessage();
			}
		}
	}

	/**
	 * Install secuence
	 */
	public function install()
	{
		$this->load->model("extension/payment/{$this->gateway_name}");
		$this->model_extension_payment_compropago_cash->install();
	}

	/**
	 * Get the base path url of the site
	 * @return string
	 */
	private function site_url()
	{
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
			? "https://"
			: "http://";
		$domainName = $_SERVER['HTTP_HOST'];
		return $protocol.$domainName;
	}
}
