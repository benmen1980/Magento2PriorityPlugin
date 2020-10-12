<?php 
namespace Priority\Api\Observer;

use Magento\Framework\Event\ObserverInterface;

class CustomerAdminCreate implements ObserverInterface
{
	protected $_request;
	protected $_layout;
	protected $_objectManager = null;
	protected $_customerGroup;
	private $logger;
	protected $_customerRepositoryInterface;

	public function __construct(
		\Magento\Framework\View\Element\Context $context,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction
	){
		$this->_layout = $context->getLayout();
		$this->_request = $context->getRequest();
		$this->_objectManager = $objectManager;
		$this->logger = $logger;
		$this->_customerRepositoryInterface = $customerRepositoryInterface;
		$this->scopeConfig = $scopeConfig;
		$this->_transactions = $transaction;
	}


	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$event = $observer->getEvent();
		$customer = $observer->getData('customer');
		$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
		$objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$username = $this->scopeConfig->getValue("settings/general/username", $storeScope);
		$password = $this->scopeConfig->getValue("settings/general/password", $storeScope);
		$application = $this->scopeConfig->getValue("settings/general/application", $storeScope);  
		$enviroment = $this->scopeConfig->getValue("settings/general/environment_name", $storeScope); 
		$url = $this->scopeConfig->getValue("settings/general/url", $storeScope);
		$ssl_verify = $this->scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
		$language = $this->scopeConfig->getValue("settings/general/language", $storeScope);
		$appId = $this->scopeConfig->getValue("settings/general/app_id",$storeScope);
		$appKey = $this->scopeConfig->getValue("settings/general/app_key",$storeScope);
		$headers = array('Content-Type: application/json');
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}	
		$additional = "/CUSTOMERS";
		$firstname = $customer->getFirstName();
		$lastname = $customer->getLastName();
		$middlename = $customer->getMiddleName();
		$email = $customer->getEmail();
		if($middlename != ""){
			$name = $firstname." ".$middlename." ".$lastname;
		} else {
			$name = $firstname." ".$lastname;
		}
		$params = array(
			"CUSTNAME" => $customer->getId(),
			"CUSTDES"  => $name,
			"PHONE"    => "",
			"EMAIL"	   => $email,
			"ADDRESS"  => "",
			"ADDRESS2" => "",
			"STATEA"   => "" 
		);
		$json_request = json_encode($params);
		$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
		$ch = curl_init($request_uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'X-App-Id:'.$appId,
			'X-App-Key:'.$appKey 
		));
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
		
		if($httpCode == '200' || $httpCode == '201')
		{
			$status = "Success";
			$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
		} else {
			$status = "Failed";
			$json_pretty = $response;
		}
		$json_request = json_encode(json_decode($json_request), JSON_PRETTY_PRINT);
		$model = $this->_transactions->create();
		$model->addData([
			"url" => $request_uri,
			"request_method" => 'POST',
			"json_request" => $json_request,
			"json_response" => $json_pretty,
			"status" => $status,
			"transaction_date" => $objDate->gmtDate()
			]);
		$saveData = $model->save();
		return $this;
	}
}