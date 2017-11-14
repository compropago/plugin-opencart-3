<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

class ModelExtensionPaymentCpCash extends Model
{
    /**
     * Compropago query tables
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function install() {
        $tables = array(
            'CREATE TABLE `' . DB_PREFIX . 'compropago_orders` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`date` int(11) NOT NULL,
			`modified` int(11) NOT NULL,
			`compropagoId` varchar(50) NOT NULL,
			`compropagoStatus`varchar(50) NOT NULL,
			`storeCartId` varchar(255) NOT NULL,
			`storeOrderId` varchar(255) NOT NULL,
			`storeExtra` varchar(255) NOT NULL,
			`ioIn` mediumtext,
			`ioOut` mediumtext,
			PRIMARY KEY (`id`), UNIQUE KEY (`compropagoId`)
			)ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;',
            'CREATE TABLE `' . DB_PREFIX . 'compropago_transactions` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`orderId` int(11) NOT NULL,
			`date` int(11) NOT NULL,
			`compropagoId` varchar(50) NOT NULL,
			`compropagoStatus` varchar(50) NOT NULL,
			`compropagoStatusLast` varchar(50) NOT NULL,
			`ioIn` mediumtext,
			`ioOut` mediumtext,
			PRIMARY KEY (`id`)
			)ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;',
            'CREATE TABLE `' . DB_PREFIX . 'compropago_webhook_transactions` (
			`id` integer not null auto_increment,
			`webhookId` varchar(50) not null,
			`webhookUrl` varchar(300) not null,
			`updated` integer not null,
			`status` varchar(50) not null,
			primary key(id)
			)ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;'
        );

        foreach ($tables as $table) {
            $this->db->query($table);
        }
    }

    /**
     * Compropago drop tables
     *
     * @author Eduardo Aguilar <dante.aguilar41@gmail.com>
     */
    public function uninstall() {
        $tables = array(
            'DROP TABLE IF EXISTS `' . DB_PREFIX . 'compropago_orders`;',
            'DROP TABLE IF EXISTS `' . DB_PREFIX . 'compropago_transactions`;',
            'DROP TABLE IF EXISTS `' . DB_PREFIX . 'compropago_webhook_transactions`'
        );

        foreach ($tables as $table) {
            $this->db->query($table);
        }
    }
}