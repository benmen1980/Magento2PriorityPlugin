<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\App\Action\Context;

class Order extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $_transaction;
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Catalog\Model\Product $product,
		\Magento\Sales\Model\Order $orderModel
    ) {
        parent::__construct($context);
		$this->scopeConfig = $scopeConfig;
		$this->_logger = $logger;
		$this->_transactions = $transaction;
		$this->_product = $product;
		$this->_orderModel = $orderModel;
    }
    public function execute()
    {
		$orders = $this->_orderModel->getCollection();
    	$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$username = $this->scopeConfig->getValue("settings/general/username", $storeScope);
		$password = $this->scopeConfig->getValue("settings/general/password", $storeScope);
		$application = $this->scopeConfig->getValue("settings/general/application", $storeScope);  
		$enviroment = $this->scopeConfig->getValue("settings/general/environment_name", $storeScope); 
		$language = $this->scopeConfig->getValue("settings/general/language", $storeScope);
		$url = $this->scopeConfig->getValue("settings/general/url", $storeScope);
		$ssl_verify = $this->scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
		$ship = $this->scopeConfig->getValue("general_settings/general_config/sku_shippment_item", $storeScope);
		$log = $this->scopeConfig->getValue("general_settings/configurable_cron_syncorder/ordersynclog",$storeScope);
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}		
		$additional = "/ORDERS";
		$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
	
		foreach($orders as $order){
			$orderid = $order->getIncrementId();
			if($order->getCustomerId() == ""){
				$customerid = $this->scopeConfig->getValue("general_settings/more_settings_config/walk_in_customer", $storeScope);
			} else {
				$customerid = $order->getCustomerId();
			}
			$orderItems = $order->getAllItems();
			$orderitem = array();
			foreach ($order->getAllItems() as $item) {	
				$items['PARTNAME'] = $item->getSku();
				$items['TQUANT'] = (int)$item->getQtyOrdered();
				$items['VPRICE'] = floatval($item->getPrice());
				array_push($orderitem,$items);
				$shipcharge = array(
					"PARTNAME" => $ship,
					"TQUANT" => 1,
					"VPRICE" => floatval($order->getShippingAmount())		
				);
				array_push($orderitem,$shipcharge);
				$params = array(
					"CUSTNAME" => $customerid,
					"CURDATE"  => date("Y-m-d"),
					"BOOKNUM"  => $orderid,
					"ORDERITEMS_SUBFORM" => $orderitem,
					"DETAILS"  => $orderid
				);
				$json_request = json_encode($params);
				$ch = curl_init($request_uri);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl);
				$response = curl_exec($ch);
				
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);
				if($httpCode == '200')
				{
					$status = "Success";
					$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
				} else {
					$status = "Failed";
					$json_pretty = $response;
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
	}
}