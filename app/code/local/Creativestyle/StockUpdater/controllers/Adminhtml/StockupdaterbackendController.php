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
    	$mage_csv->setDelimiter(',');
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
    	foreach ($data as $_data) {
    		$this->updateProduct($_data);
    	}
    }

    private function updateProduct($data){
		$_product = Mage::getModel('catalog/product')->loadByAttribute('sku', $data[0]);
		if(!$_product) {
			$errormsg = 'Product of sku: '.$data[0].' doesnt exist.';
			$this->addMsg('error',$errormsg); return;
		}
		$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product->getId());
		$stockItem->setData('qty',$data[1]);
		$stockItem->setData('is_in_stock',$data[2]);
		$stockItem->save();
		$_product->save();
    }

    protected function _getProductCollection()
	{
    $collection = Mage::getModel('catalog/product')->getCollection()
							->addAttributeToSelect('*');
    return $collection;
	}


}
