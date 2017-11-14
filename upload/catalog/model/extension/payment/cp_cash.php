<?php

class ModelExtensionPaymentCpCash extends Model
{
    public function getMethod($address, $total) {
        $this->language->load('extension/payment/cp_cash');

        return array(
            'code'       => 'cp_cash',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_cp_cash_sort_order')
        );
    }
}