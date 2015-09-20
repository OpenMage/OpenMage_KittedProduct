<?php

class OpenMage_KittedProduct_Model_Product_Observer
{
    public function afterAddToQuote(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote_Item[] $items */
        $items = $observer->getData('items');
        if (count($items) <= 1) {
            return;
        }

        $parent = array_shift($items);

        if ($parent instanceof Mage_Sales_Model_Quote_Item && $parent->getProductType() === OpenMage_KittedProduct_Model_Product_Type::TYPE_CODE) {
            $priceModel = $parent->getProduct()->getPriceModel();
            foreach ($items as $item) {
                if ($item->getParentItem() !== $parent) {
                    continue;
                }
                $originalPrice = $item->getProduct()->getPriceModel()->getFinalPrice($item->getQty(), $item->getProduct());
                $finalPrice = $priceModel->getChildFinalPrice($parent->getProduct(), $parent->getQty(), $item->getProduct(), $item->getQty());
                $item->setPrice($finalPrice);
                $item->setBaseOriginalPrice($originalPrice);
            }
        }
    }
}
