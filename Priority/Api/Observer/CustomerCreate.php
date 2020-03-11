<?php 
namespace Priority\Api\Observer;

use Magento\Framework\Event\ObserverInterface;
 
class CustomerCreate implements ObserverInterface
{

	protected $scopeConfig;
	protected $storeManager;
	
    public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Priority\Api\Model\TransactionsFactory  $transaction
    ) {
		$this->scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->_transactions = $transaction;
	}
 
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
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
		$language = $this->_scopeConfig->getValue("settings/general/language", $storeScope);
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
		$billingAddressId = $customer->getDefaultBilling();
		$address = $objectManager->get('Magento\Customer\Model\AddressFactory')->create()->load($billingAddressId);
		$street = $address->getStreet();				
		$city = $address->getCity();
		$telephone = $address->getTelephone();
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
			"ADDRESS"  => $address->getStreet(1),
			"ADDRESS2" => $address->getStreet(2),
			"STATEA"   => $city 
		);
		$json_request = json_encode($params);
		$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
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
	}
}