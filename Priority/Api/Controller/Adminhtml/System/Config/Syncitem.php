<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class Syncitem extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $resultJsonFactory;
	
	protected $_transaction;
	
    protected $resultRedirect;	
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Controller\ResultFactory $result,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		\Magento\Catalog\Model\Product $product,
		\Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        parent::__construct($context);
		$this->_scopeConfig = $scopeConfig;
		$this->_logger = $logger;
		$this->_transactions = $transaction;
        $this->resultRedirect = $result;
		$this->_messageManager = $messageManager;
		$this->product = $product;
		$this->productFactory = $productFactory;
	
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
        $log = $this->_scopeConfig->getValue("general_settings/configurable_cron_syncitem/inventorysynclog",$storeScope);
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}		
		
		$additional = "/LOGPART";
		
		
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
			$name = $rdata['PARTDES'];
			$price = $rdata['VATPRICE'];
			$urlkey = $name."-".$sku;
			$product = $objectManager->create('\Magento\Catalog\Model\Product');
			if(!$product->getIdBySku($sku)){
				$product->setSku($sku); 
				$product->setName($name); 
				$product->setAttributeSetId(4); 
				$product->setStatus(1); 
				$product->setWeight(10); 
				$product->setVisibility(4);
				$product->setTypeId('simple'); 
				$product->setPrice($price);
				$product->setWebsiteIds(array(1));
				$product->setUrlKey($urlkey);
				$product->setStockData(
						array(
							'use_config_manage_stock' => 0,
							'manage_stock' => 1,
							'is_in_stock' => 1,
							'qty' => 0
						)
					);
				$product->save();
			} else {
				//echo $sku."</br>";
				$existproduct = $this->productFactory->create();
				$existproduct->load($existproduct->getIdBySku($sku));
				//echo $existproduct->getName()."</br>";
				$existproduct->setName($name); 
				$existproduct->setPrice($price);
				$existproduct->setStoreId(0);
				$existproduct->save();
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