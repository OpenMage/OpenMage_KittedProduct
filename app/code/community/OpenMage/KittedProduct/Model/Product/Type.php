<?php

class OpenMage_KittedProduct_Model_Product_Type extends Mage_Catalog_Model_Product_Type_Grouped
{
    const TYPE_CODE = 'om_kitted';

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
}
