<?php

class ModelExtensionPaymentCompropagoCash extends Model {

    public function install() {
        $row = $this->db->query("
            SELECT count(*) as num_exists
            FROM information_schema.COLUMNS
            WHERE
                TABLE_SCHEMA = '" . DB_DATABASE . "'
            AND TABLE_NAME = '" . DB_PREFIX . "order'
            AND COLUMN_NAME = 'compropago_data'"
        );

        if (count($row) == 0) {
            $this->db->query("alter table " . DB_PREFIX . "order add column compropago_data text default null");
        }
    }


}