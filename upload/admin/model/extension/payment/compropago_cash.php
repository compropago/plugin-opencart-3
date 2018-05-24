<?php

class ModelExtensionPaymentCompropagoCash extends Model {
    /**
     * Add ComproPago Column for transaction data in orders table
     */
    public function install() {
        try {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD COLUMN compropago_data TEXT DEFAULT NULL");
        } catch (Exception $e) {
        }
    }
}