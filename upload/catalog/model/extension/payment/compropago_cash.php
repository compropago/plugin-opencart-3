<?php
class ModelExtensionPaymentCompropagoCash extends Model {
    /**
     * @param $address
     * @param $total
     * @return array
     */
    public function getMethod($address, $total) {
        $this->language->load('extension/payment/compropago_cash');

        $title = '<img src="https://compropago.com/plugins/compropago-efectivo-v2.svg" style="height: 20px;">';
        $title .= ' - ' . $this->config->get('payment_compropago_cash_title');

        return array(
            'code'       => 'compropago_cash',
            'title'      => $title,
            'terms'      => '',
            'sort_order' => $this->config->get('payment_compropago_cash_cash_sort_order')
        );
    }
}