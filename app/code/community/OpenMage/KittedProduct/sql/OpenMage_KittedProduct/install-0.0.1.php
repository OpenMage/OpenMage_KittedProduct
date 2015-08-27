<?php
/** @var Mage_Catalog_Model_Resource_Setup $this */
$this->startSetup();

$updateAttributes = ['price', 'group_price', 'tier_price', 'special_price', 'special_from_date', 'special_to_date'];
foreach ($updateAttributes as $attributeName) {
    $attribute = $this->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeName);
    $applyTo = array_filter(array_map('trim', explode(',', $attribute['apply_to'])));
    $applyTo[] = OpenMage_KittedProduct_Model_Product_Type::TYPE_CODE;
    $applyTo = implode(',', array_unique($applyTo));
    $this->updateAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeName, 'apply_to', $applyTo);
}

$this->endSetup();
