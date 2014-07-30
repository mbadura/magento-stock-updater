<?php
class Creativestyle_StockUpdater_Block_Adminhtml_Catalog_Product extends Mage_Adminhtml_Block_Catalog_Product
{

    public function __construct() {

        parent::__construct();

        $this->_addButton('stock_csv_import', array(
            'label'   => Mage::helper('catalog')->__('Import Stock CSV'),
            'onclick' => "setLocation('{$this->getUrl('stockupdater/catalog/product/exportcsv/')}')",
            'class'   => 'go'
        ));
    }


}
