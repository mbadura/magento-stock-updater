<?php
class Creativestyle_StockUpdater_Adminhtml_StockupdaterbackendController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
       Mage::getSingleton('core/session')->addNotice('Proper file format is .CSV');
       Mage::getSingleton('core/session')->addNotice('For example data do the "export" first');
       $this->loadLayout();
       $this->_title($this->__("StockUpdater"));
       $this->renderLayout();
    }




    private function _getConnection($type = 'core_read'){
        return Mage::getSingleton('core/resource')->getConnection($type);
    }

    private function _getTableName($tableName){
        return Mage::getSingleton('core/resource')->getTableName($tableName);
    }

    private function _checkIfSkuExists($sku){
        $connection = $this->_getConnection('core_read');
        $sql        = "SELECT COUNT(*) AS count_no FROM " . $this->_getTableName('catalog_product_entity') . " WHERE sku = ?";
        $count      = $connection->fetchOne($sql, array($sku));
        if($count > 0){
            return true;
        }else{
            return false;
        }
    }

    private function _getIdFromSku($sku){

        $connection = $this->_getConnection('core_read');
        $sql        = "SELECT entity_id FROM " . $this->_getTableName('catalog_product_entity') . " WHERE sku = ?";

        return $connection->fetchOne($sql, array($sku));
    }


    private function _updateStocks($data,$fulldata){

        $_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $data[0]);

         if($_product->getTypeId()=='configurable') {
            $status = $this->checkConfigurable($_product,$fulldata);
            if($status == '0') { $isInStock = '0'; }
            elseif($status == '1') { $isInStock = '1'; }
        } else {
              $isInStock      = $data[2];
         }


        $connection     = $this->_getConnection('core_write');
        $sku            = $data[0];
        $newQty         = $data[1];
        $productId      = $this->_getIdFromSku($sku);

        $sql            = "UPDATE " . $this->_getTableName('cataloginventory_stock_item') . " csi," . $this->_getTableName('cataloginventory_stock_status') . " css
                           SET
                           csi.qty = " . (int)$newQty . ",
                           csi.is_in_stock = " . (int)$isInStock . ",
                           css.qty = " . (int)$newQty . "
                           WHERE
                           csi.product_id = " . (int)$productId . "
                           AND csi.product_id = css.product_id";

        $connection->query($sql);

    }

    public function postAction() {

        if(isset($_POST['exportcsv'])){
            $this->exportCSV();
        }

        elseif(isset($_POST['importcsv'])){
            $uploader = new Varien_File_Uploader('csvfile');
            $this->importCSV($uploader);
        }

        $this->_redirect('*/*/');

    }

    private function checkUploadType() {
        $updater = $this->getRequest()->getParam('sqlquery');

        if($updater) { return true; }
        else {
            return false;
        }
    }

    private function exportCSV() {

        $collection = $this->_getProductCollection();
        $this->saveCSVfile($collection);
    }

    private function importCSV($uploader) {
        $path = Mage::getBaseDir('media');
        $fileName = $_FILES['csvfile']['name'];

        $uploader->save($path, $fileName);

        $this->addMsg('success','File successfully uploaded');
        $this->readCsvFile($path,$fileName);
    }

    private function readCsvFile($path,$file) {
        $csv = new Varien_File_Csv();
        $csv->setDelimiter(';');
        $data = $csv->getData($path.'/'.$file);

        $this->updateProducts($data);
    }

    private function saveCSVFile($collection) {
        $date = date('Y-m-d');
        $fileName = 'productStockData_'.$date.'.csv';
        $file_path = Mage::getBaseDir('media').'/'.$fileName;
        $mage_csv = new Varien_File_Csv();
        $mage_csv->setDelimiter(';');
        $mage_csv->setEnclosure('');

        $data = $this->getProductData($collection);

        $mage_csv->saveData($file_path, $data);

        $link = $this->getDownloadLink($file_path);

        $this->addMsg('success','Successfully generated csv file in /media.');
        $this->addMsg('success',$link);

    }

    protected function getDownloadLink($file_path) {
        $link = '<a href="'.$file_path.'"> Download link </a>';
        return $link;
    }

    private function getProductData($collection) {
        $data = array();
        foreach ($collection as $_product) {
            $smalldata = array();
            $smalldata['sku'] = $_product->getSku();

            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);

            $smalldata['qty'] = $stock->getQty();
            $smalldata['is_in_stock'] = $stock->getIsInStock();

            $data[] = $smalldata;
        }

        return $data;
    }

    private function addMsg($type,$msg) {
        $text = $this->__($msg);
        switch ($type) {
            case 'success':
                Mage::getSingleton('adminhtml/session')->addSuccess($msg);
                break;
            case 'error':
                Mage::getSingleton('adminhtml/session')->addError($msg);
                break;
        }
    }



    private function updateProducts($data) {

        if($this->checkUploadType()) {
            foreach($data as $_data) {
                $this->_updateStocks($_data,$data);
            }

            $this->addMsg('success','Do it sql way');

        } else {
        foreach ($data as $_data) {
            $this->updateProduct($_data,$data);
        }

        }
    }

    private function checkConfigurable($_product,$data) {

        $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($_product);
        $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();

        $status='0';

        foreach($data as $_data) {
            if($status=='1'){ break; }
          foreach($simple_collection as $simple_product) {
            if($simple_product->getSku() == $_data[0] && $_data[2] == '1') {
                $status='1';
                break;
            }
          }
        }
        return $status;
    }

    private function updateProduct($data,$fulldata){
        $_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $data[0]);

        if(!$_product) {
            $errormsg = 'Product of sku: '.$data[0].' doesnt exist.';
            $this->addMsg('error',$errormsg); return;
        }

        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product->getId());

        if($_product->getTypeId()=='configurable') {
            $status = $this->checkConfigurable($_product,$fulldata);
            $stockItem->setData('qty',$data[1]);
            if($status == '0') {
                $stockItem->setData('is_in_stock','0');
            } elseif($status == '1') {
                $stockItem->setData('is_in_stock','1');
            }
            $stockItem->save();
        } else {
        $stockItem->setData('qty',$data[1]);
        $stockItem->setData('is_in_stock',$data[2]);
        $stockItem->save();
        }
        $_product->save();
    }

    protected function _getProductCollection()
    {
    $collection = Mage::getModel('catalog/product')->getCollection()
                            ->addAttributeToSelect('*');
    return $collection;
    }



}
