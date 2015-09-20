<?php

class OpenMage_KittedProduct_Model_Product_Type extends Mage_Catalog_Model_Product_Type_Abstract
{
    const TYPE_CODE = 'om_kitted';

    /**
     * Cache key for linked products
     *
     * @var string
     */
    protected $_keyAssociatedProducts = '_cache_instance_kit_products';

    /**
     * Product is a composite product
     *
     * @var bool
     */
    protected $_isComposite = true;

    /**
     * Return relation info about used products
     *
     * @return Varien_Object Object with information data
     */
    public function getRelationInfo()
    {
        return new Varien_Object(
            [
                'table'             => 'catalog/product_link',
                'parent_field_name' => 'product_id',
                'child_field_name'  => 'linked_product_id',
                'where'             => 'link_type_id=' . Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED,
            ]
        );
    }

    /**
     * Retrieve grouped required children ids
     *
     * @param int  $parentId
     * @param bool $required
     *
     * @return int[][]
     */
    public function getChildrenIds($parentId, $required = true)
    {
        $childIds = [];

        /** @var Mage_Catalog_Model_Resource_Product_Link $linkResource */
        $linkResource = Mage::getResourceSingleton('catalog/product_link');
        $linkChildIds = $linkResource->getChildrenIds($parentId, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
        foreach ($linkChildIds[Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED] as $linkChildId) {
            $childIds[] = [$linkChildId];
        }

        return $childIds;
    }

    /**
     * Retrieve parent ids array by requered child
     *
     * @param int|int[] $childId
     *
     * @return array
     */
    public function getParentIdsByChild($childId)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Link $linkResource */
        $linkResource = Mage::getResourceSingleton('catalog/product_link');

        return $linkResource->getParentIdsByChild($childId, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
    }

    /**
     * Check if product is available for sale
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return bool
     */
    public function isSalable($product = null)
    {
        $salable = parent::isSalable($product);

        if ($salable) {
            foreach ($this->getAssociatedProducts($product) as $associatedProduct) {
                $salable = $salable && $associatedProduct->isSalable();
            }
        }

        return $salable;
    }

    /**
     * Retrieve collection of associated products
     *
     * @param Mage_Catalog_Model_Product|null $product
     *
     * @return Mage_Catalog_Model_Resource_Product_Link_Product_Collection
     */
    public function getAssociatedProductCollection($product = null)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Link_Product_Collection $collection */
        $collection = $this->getProduct($product)->getLinkInstance()->useGroupedLinks()->getProductCollection();

        $collection->setFlag('require_stock_items', true);
        $collection->setFlag('product_children', true);
        $collection->setIsStrongMode();
        $collection->setProduct($this->getProduct($product));

        return $collection;
    }

    /**
     * Retrieve array of associated products
     *
     * @param Mage_Catalog_Model_Product|null $product
     * @param bool|null                       $showDisabled
     *
     * @return Mage_Catalog_Model_Product[]
     */
    public function getAssociatedProducts($product = null, $showDisabled = null)
    {
        if ($showDisabled === null) {
            $showDisabled = Mage::app()->getStore()->isAdmin();
        }

        $showDisabled = (bool)$showDisabled;

        $dataKey = $this->_keyAssociatedProducts . intval($showDisabled);

        if (!$this->getProduct($product)->hasData($dataKey)) {
            $associatedProducts = [];

            $collection = $this->getAssociatedProductCollection($product);
            $collection->addAttributeToSelect('*');
            $collection->addFilterByRequiredOptions();
            $collection->setPositionOrder();
            $collection->addStoreFilter($this->getStoreFilter($product));

            if (!$showDisabled) {
                $collection->addAttributeToFilter('status', ['eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED]);
            }

            foreach ($collection as $item) {
                $associatedProducts[] = $item;
            }

            $this->getProduct($product)->setData($dataKey, $associatedProducts);
        }

        return $this->getProduct($product)->getData($dataKey);
    }

    /**
     * Retrieve related products identifiers
     *
     * @param Mage_Catalog_Model_Product|null $product
     * @param bool|null                       $showDisabled
     *
     * @return int[]
     */
    public function getAssociatedProductIds($product = null, $showDisabled = null)
    {
        $associatedProductIds = [];

        foreach ($this->getAssociatedProducts($product, $showDisabled) as $item) {
            $associatedProductIds[] = $item->getId();
        }

        return $associatedProductIds;
    }

    /**
     * Save type related data
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return $this
     */
    public function save($product = null)
    {
        parent::save($product);

        $this->getProduct($product)->getLinkInstance()->saveGroupedLinks($this->getProduct($product));

        return $this;
    }

    /**
     * Prepare product and its configuration to be added to some products list.
     * Perform standard preparation process and add logic specific to kitted product type.
     *
     * @param Varien_Object              $buyRequest
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $processMode
     *
     * @return Mage_Catalog_Model_Product[]|string
     */
    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode)
    {
        /** @var Mage_Catalog_Model_Product[] $products */
        $products = Mage_Catalog_Model_Product_Type_Abstract::_prepareProduct($buyRequest, $product, $processMode);
        if (!is_array($products)) {
            return $products;
        }
        if (empty($products)) {
            return Mage::helper('OpenMage_KittedProduct/Data')->__('Cannot process the item.');
        }

        /** @var Mage_Catalog_Model_Product $parentProduct */
        $parentProduct = $products[0];

        /** @var Mage_Catalog_Model_Product[] $associatedProducts */
        $associatedProducts = $this->getAssociatedProducts($product);
        if ($associatedProducts && $this->_isStrictProcessMode($processMode)) {
            foreach ($associatedProducts as $subProduct) {
                $buyRequest->setQty($parentProduct->getQty() * $subProduct->getQty());
                $result = $subProduct->getTypeInstance(true)->processConfiguration($buyRequest, $subProduct, $processMode);
                if (!is_array($result)) {
                    return $result;
                }

                if (empty($result)) {
                    return Mage::helper('OpenMage_KittedProduct/Data')->__('Cannot process the item.');
                }

                /** @var Mage_Catalog_Model_Product $subProduct */
                $subProduct = reset($result);
                $subProduct->setParentProductId($parentProduct->getId());
                $subProduct->setCartQty($subProduct->getQty());

                $parentProduct->addCustomOption('product_qty_' . $subProduct->getId(), $subProduct->getQty(), $subProduct);

                $products[] = $subProduct;
            }
        }

        return $products;
    }

    /**
     * Retrieve products divided into groups required to purchase
     * At least one product in each group has to be purchased
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return Mage_Catalog_Model_Product[][]
     */
    public function getProductsToPurchaseByReqGroups($product = null)
    {
        // Ensure we have a reference product
        $product = $this->getProduct($product);

        $groups = [];
        foreach ($this->getAssociatedProducts($product) as $childProduct) {
            // All children are required so each one gets it's own group
            $groups[] = [$childProduct];
        };

        return $groups;
    }


    /**
     * Prepare additional options/information for order item which will be
     * created from this product
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return array
     */
    public function getOrderOptions($product = null)
    {
        $optionArr = parent::getOrderOptions($product);

        $optionArr['shipment_type'] = Mage_Catalog_Model_Product_Type_Abstract::SHIPMENT_SEPARATELY;

        return $optionArr;
    }
}
