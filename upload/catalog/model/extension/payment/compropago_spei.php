<?php

require_once __DIR__ . '/../../../../system/library/compropago/vendor/autoload.php';


class ModelExtensionPaymentCompropagoSpei extends Model
{
    const GATEWAY_NAME = 'compropago_spei';
    const GATEWAY_LOGO = 'https://compropago.com/plugins/compropago-spei-v2.svg';

    /**
     * Render Payment option in checkout
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total)
    {
        $this->language->load( 'extension/payment/' . self::GATEWAY_NAME );

        $title = '<img src="' . self::GATEWAY_LOGO . '" style="height: 20px;">'
                . ' ' . $this->config->get('payment_' . self::GATEWAY_NAME . '_title');

        return [
            'code'       => self::GATEWAY_NAME,
            'title'      => $title,
            'terms'      => '',
            'sort_order' => $this->config->get('payment_' . self::GATEWAY_NAME . '_sort_order')
        ];
    }
}
