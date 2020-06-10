<?php 
namespace Priority\Api\Observer;

use Magento\Framework\Event\ObserverInterface;
 
class CustomerAddressUpdate implements ObserverInterface
{
	protected $scopeConfig;
	protected $storeManager;
	
    public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\App\RequestInterface $request
    ) {
		$this->scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->_transactions = $transaction;
		$this->_request = $request;
	}
	public function execute(\Magento\Framework\Event\Observer $observer)
    {
		$postData = $this->_request->getPost();
        $customerAddress = $observer->getCustomerAddress();
		$customer = $customerAddress->getCustomer();
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
		$firstname = $postData['firstname'];
		$lastname = $postData['lastname'];
		$middlename = $postData['middlename'];
		$email = $customer->getEmail();
		$street = $customerAddress->getStreetFull();				
		$city = $customerAddress->getCity();
		$telephone = $customerAddress->getTelephone();
		if($middlename != ""){
			$name = $firstname." ".$middlename." ".$lastname;
		} else {
			$name = $firstname." ".$lastname;
		}
		$params = array(
			"CUSTNAME" => $customer->getId(),
			"CUSTDES"  => $name,
			"PHONE"    => $telephone,
			"EMAIL"	   => $email,
			"ADDRESS"  => $street,
			"ADDRESS2" => "",
			"STATEA"   => $city 
		);
		$json_request = json_encode($params,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if($httpCode == '200' || $httpCode == '201')
		{
			$status = "Success";
			$json_pretty = json_encode(json_decode($response), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		} else {
			$status = "Failed";
			$json_pretty = $response;
		}
		
		$json_request = json_encode(json_decode($json_request),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$model = $this->_transactions->create();
		$model->addData([
			"url" => $request_uri,
			"request_method" => 'PATCH',
			"json_request" => $json_request,
			"json_response" => $json_pretty,
			"status" => $status,
			"transaction_date" => $objDate->gmtDate()
			]);
		$saveData = $model->save();
    }
}