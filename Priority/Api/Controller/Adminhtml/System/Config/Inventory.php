<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;

class Inventory extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $resultJsonFactory;
	
	protected $_transaction;
	
    protected $resultRedirect;	
	
	protected $_sourceItemsSaveInterface;

    protected $_sourceItemFactory;
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Controller\ResultFactory $result,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		\Magento\Catalog\Model\Product $product,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
		SourceItemsSaveInterface $sourceItemsSaveInterface,
        SourceItemInterfaceFactory $sourceItemFactory
    ) {
        parent::__construct($context);
		$this->_scopeConfig = $scopeConfig;
		$this->_logger = $logger;
		$this->_transactions = $transaction;
        $this->resultRedirect = $result;
		$this->_messageManager = $messageManager;
		$this->_product = $product;
        $this->_stockStateInterface = $stockStateInterface;
        $this->_stockRegistry = $stockRegistry;
		$this->_sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->_sourceItemFactory = $sourceItemFactory;
    }
    public function execute()
    {
		
    	$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');

		//$resultJson = $this->resultJsonFactory->create();
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$username = $this->_scopeConfig->getValue("settings/general/username", $storeScope);
		$password = $this->_scopeConfig->getValue("settings/general/password", $storeScope);
		$application = $this->_scopeConfig->getValue("settings/general/application", $storeScope);  
		$enviroment = $this->_scopeConfig->getValue("settings/general/environment_name", $storeScope); 
		$language = $this->_scopeConfig->getValue("settings/general/language", $storeScope);
		$url = $this->_scopeConfig->getValue("settings/general/url", $storeScope);
		$ssl_verify = $this->_scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
		$log = $this->_scopeConfig->getValue("general_settings/configurable_cron_syncinventory/inventorysynclog",$storeScope);
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}		
		
		$additional = '/LOGPART?&$expand=LOGCOUNTERS_SUBFORM';
		
		
		$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
		$curl = curl_init($request_uri);
		curl_setopt($curl, CURLOPT_URL, $request_uri);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $ssl); 
		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
		curl_close($curl);
		$responsedata = json_decode($response,true);
		foreach($responsedata['value'] as $rdata){
			$sku = $rdata['PARTNAME'];
			if(!empty($rdata['LOGCOUNTERS_SUBFORM'])){
				$qty = $rdata['LOGCOUNTERS_SUBFORM'][0]['BALANCE'];
				$sourceList = $objectManager->get('\Magento\Inventory\Model\ResourceModel\Source\Collection');
				$sourceListArr = $sourceList->load();
				foreach ($sourceListArr as $sourceItemName) {
					$sourceItem = $this->_sourceItemFactory->create();
				    $sourceItem->setSourceCode($sourceItemName->getSourceCode());
				    $sourceItem->setSku($sku);
				    $sourceItem->setQuantity($qty);
				    $sourceItem->setStatus(1);
				    $this->_sourceItemsSaveInterface->execute([$sourceItem]);
				}
				//$stockItem = $this->_stockRegistry->getStockItemBySku($sku);
				//$stockItem->setData('is_in_stock',(bool)$qty);
				//$stockItem->setData('qty',$qty);
				//$stockItem->setData('manage_stock',1);
				//$stockItem->setData('use_config_notify_stock_qty',1);
				//$this->_stockRegistry->updateStockItemBySku($sku, $stockItem);
			}
		}
		if($httpCode == '200')
		{
			$status = "Success";
			$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
			$this->_messageManager->addSuccess('API Items Sync Successfully.');
		} else {
			$status = "Failed";
			$json_pretty = $response;
			$this->_messageManager->addError('Something Wrong');
		}	
		
		if($log == 1){
			$model = $this->_transactions->create();
			$model->addData([
				"url" => $request_uri,
				"request_method" => "GET",
				"json_request" => "",
				"json_response" => $json_pretty,
				"status" => $status,
				"transaction_date" => $objDate->gmtDate()
				]);
			$model->save();
		}
	}
}