<?php

class OpenMage_KittedProduct_Resource_Indexer_Stock extends Mage_CatalogInventory_Model_Resource_Indexer_Stock_Default
{
    /**
     * Get the select object for get stock status by product ids
     *
     * @param int|int[] $entityIds
     * @param bool      $usePrimaryTable use primary or temporary index table
     *
     * @return Varien_Db_Select
     */
    protected function _getStockStatusSelect($entityIds = null, $usePrimaryTable = false)
    {
        $adapter = $this->_getWriteAdapter();

        $select = $adapter->select();

        // Select the entity_id from the product table
        $select->from(['e' => $this->getTable('catalog/product')], ['entity_id']);
        $select->where('e.type_id = ?', $this->getTypeId());

        // Join to the website table
        $this->_addWebsiteJoinToSelect($select, true);
        $this->_addProductWebsiteJoinToSelect($select, 'cw.website_id', 'e.entity_id');
        $select->columns('cw.website_id');
        $select->where('cw.website_id != 0');

        // Join to stock item entries
        $select->joinLeft(
            ['cisi' => $this->getTable('cataloginventory/stock_item')],
            'cisi.product_id = e.entity_id',
            ['stock_id']
        );

        // Join to linked products (link -> product -> indexer status)
        $select->joinLeft(
            ['l' => $this->getTable('catalog/product_link')],
            'e.entity_id = l.product_id AND l.link_type_id=' . Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED,
            []
        );
        $select->joinLeft(
            ['lp' => $this->getTable('catalog/product')],
            'l.linked_product_id = lp.entity_id',
            []
        );
        $select->joinLeft(
            ['lpis' => ($usePrimaryTable ? $this->getMainTable() : $this->getIdxTable())],
            'l.linked_product_id = lpis.product_id AND cw.website_id = lpis.website_id AND cisi.stock_id = lpis.stock_id',
            []
        );

        // Join link quantity
        $select->joinLeft(
            ['la' => $this->getTable('catalog/product_link_attribute')],
            'la.link_type_id = l.link_type_id AND la.product_link_attribute_code = "qty"',
            []
        );
        $select->joinLeft(
            ['laqd' => $this->getTable('catalog/product_link_attribute_decimal')],
            'laqd.product_link_attribute_id = la.product_link_attribute_id AND laqd.link_id = l.link_id',
            []
        );

        // Base parent qty of the lowest available child quantity divided by child units in parent
        $select->columns(['qty' => new Zend_Db_Expr('MIN(IF(laqd.value <= 0 OR laqd.value IS NULL, 0, FLOOR(lpis.qty / laqd.value)))')]);

        // Create a single entry per product_id/website_id/stock_id
        $select->group(['e.entity_id', 'cw.website_id', 'cisi.stock_id']);

        // Use the parent product enabled/disabled status (Must return 0 or 1)
        $productStatusField = $this->_addAttributeToSelect($select, 'status', 'e.entity_id', 'cs.store_id');
        $productStatusExpression = $adapter->getCheckSql($adapter->quoteInto($productStatusField . '=?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED), 1, 0);

        // Use the configured stock status for the parent product (Must return 0 or 1)
        $productStockStatusExpression = $adapter->getCheckSql(
            'cisi.use_config_manage_stock = 1',
            ($this->_isManageStock() ? 1 : 0),
            $adapter->getCheckSql('cisi.manage_stock = 1', 'cisi.is_in_stock', 1)
        );

        // Use the index stock status for child products (Must return 0 or 1) (This uses a group aggregate function due to the grouping)
        $childStockStatusExpression = 'MIN(' . $adapter->getCheckSql("lp.required_options = 0", 'lpis.stock_status', 0) . ')';

        // This picks the lowest of the three expressions. Any of the three being 0 means the product is out of stock.
        $overallStockStatusExpression = $adapter->getLeastSql(
            [
                $productStatusExpression,
                $productStockStatusExpression,
                $childStockStatusExpression,
            ]
        );
        $select->columns(['status' => $overallStockStatusExpression]);

        if (!is_null($entityIds)) {
            $entityIds = array_filter(array_map('intval', (array)$entityIds));
            $select->where('e.entity_id IN(?)', $entityIds);
        }

        return $select;
    }
}
