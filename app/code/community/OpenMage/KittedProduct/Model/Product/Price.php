<?php

class OpenMage_KittedProduct_Model_Product_Price extends Mage_Catalog_Model_Product_Type_Price
{
    /**
     * @param Mage_Catalog_Model_Product $product
     * @param float                      $productQty
     * @param Mage_Catalog_Model_Product $childProduct
     * @param float                      $childProductQty
     *
     * @return float
     */
    public function getChildFinalPrice($product, $productQty, $childProduct, $childProductQty)
    {
        // Bail if the parameters are unexpected and let the core code handle (or fail)
        if (!$product instanceof Mage_Catalog_Model_Product || !$childProduct instanceof Mage_Catalog_Model_Product) {
            return parent::getChildFinalPrice($product, $productQty, $childProduct, $childProductQty);
        }

        // If the parent product type is unexpected then bail and let core handle
        $type = $product->getTypeInstance(true);
        if (!$type instanceof OpenMage_KittedProduct_Model_Product_Type) {
            return parent::getChildFinalPrice($product, $productQty, $childProduct, $childProductQty);
        }

        // Calculate the normal, unadjusted linked items total
        $calculatedTotal = 0.0;
        foreach ($type->getAssociatedProducts($product, false) as $associatedProduct) {
            $calculatedTotal += ($associatedProduct->getPrice() * $associatedProduct->getQty());
        }

        // Generate a child price multiplier
        $priceMultiplier = ($calculatedTotal > 0.0 ? $product->getPrice() / $calculatedTotal : 1.0);

        // Generate the normal final price
        $finalPrice = $childProduct->getFinalPrice($childProductQty);

        // Multiply the final price by the multiplier to get the adjusted final price
        $finalPrice *= $priceMultiplier;

        // Round the final price
        $finalPrice = $product->getStore()->roundPrice($finalPrice);

        return $finalPrice;
    }
}
