<?php

/**
 * Class Aoe_Import_Model_Processor_Demo_Product
 *
 * Check doc/example/products.xml for an example xml file
 */
class Aoe_Import_Model_Processor_Demo_Product extends Aoe_Import_Model_Processor_Xml_Abstract
{
    /**
     * Process the xml node (available under $this->xml as SimpleXMLElement
     */
    public function process() {
        $product = Mage::getModel('catalog/product'); /* @var $product Mage_Catalog_Model_Product */

        $sku = (string)$this->xml->sku;

        $product->load($product->getIdBySku($sku)); // try loading by sku first

        if ($product->getId()) {
            $this->addInfo('Existing product with sku "%s" found. Updating...', $sku);
        } else {
            $this->addInfo('No product with sku "%s" found. Creating new one...', $sku);
        }

        foreach ($this->xml as $nodeName => $node) { /* @var $node SimpleXMLElement */
            $product->setDataUsingMethod($nodeName, (string)$node);
            $this->addInfo('Saved value "%s" to attribute "%s"', (string)$node, $nodeName);
        }

        $product->setWebsiteIDs(array(1));
        $product->setStockData(array(
            'is_in_stock' => 1,
            'qty' => 99999,
            'manage_stock' => 1
        ));
        $product->setCreatedAt(strtotime('now'));
        $product->save();
    }
}