<?php


class ModelExtensionPaymentCompropagoCash extends Model
{
    /**
     * Add ComproPago column for transaction data in orders table
     */
    public function install()
    {
        $query = "ALTER TABLE " . DB_PREFIX . "order
        ADD COLUMN compropago_data TEXT DEFAULT NULL";
        
        try
        {
            $this->db->query( $query );
        }
        catch (Exception $e)
        {
        }
    }
}
