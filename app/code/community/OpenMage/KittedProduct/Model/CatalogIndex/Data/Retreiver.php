<?php

class OpenMage_KittedProduct_Model_CatalogIndex_Data_Retriever extends Mage_CatalogIndex_Model_Data_Abstract
{
    /**
     * Defines when product type has children
     *
     * @var boolean
     */
    protected $_haveChildren = [
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_TIERS      => false,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_PRICES     => false,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_ATTRIBUTES => true,
    ];

    protected $_haveParents = false;

    /**
     * Retrieve product type code
     *
     * @return string
     */
    public function getTypeCode()
    {
        return OpenMage_KittedProduct_Model_Product_Type::TYPE_CODE;
    }

    /**
     * Get child link table and field settings
     *
     * @return mixed
     */
    protected function _getLinkSettings()
    {
        return [
            'table'        => 'catalog/product_link',
            'parent_field' => 'product_id',
            'child_field'  => 'linked_product_id',
            'additional'   => ['link_type_id' => Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED],
        ];
    }
}
