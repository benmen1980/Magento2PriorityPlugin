<?php
namespace Priority\Api\Controller\Adminhtml\Customer\Address;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class Save extends \Magento\Customer\Controller\Adminhtml\Address\Save
{
	protected $_logger;
	
	protected $_scopeConfig;
	
	protected $resultJsonFactory;
	
	protected $_transaction;
	
    protected $resultRedirect;

	protected $addressRepository;
	
	public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Controller\ResultFactory $result,
		\Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Customer\Model\Metadata\FormFactory $formFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory

    ) {
		$this->_scopeConfig = $scopeConfig;
		$this->_transactions = $transaction;
        $this->resultRedirect = $result;
		$this->customerRepository = $customerRepository;
		parent::__construct($context,$addressRepository,$formFactory,$customerRepository,$dataObjectHelper,$addressDataFactory,$logger,$resultJsonFactory);
    }
	
    public function execute() : Json
    {
		
		$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$username = $this->_scopeConfig->getValue("settings/general/username", $storeScope);
		$password = $this->_scopeConfig->getValue("settings/general/password", $storeScope);
		$application = $this->_scopeConfig->getValue("settings/general/application", $storeScope);  
		$enviroment = $this->_scopeConfig->getValue("settings/general/environment_name", $storeScope); 
		$url = $this->_scopeConfig->getValue("settings/general/url", $storeScope);
		$ssl_verify = $this->_scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
		$headers = array('Content-Type: application/json');
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}	
		$additional = "/CUSTOMERS";
		$customer = $this->customerRepository->getById($this->getRequest()->getParam('parent_id'));
		$firstname = $this->getRequest()->getParam('firstname');
		$lastname = $this->getRequest()->getParam('lastname');
		$middlename = $this->getRequest()->getParam('middlename');
		$email = $customer->getEmail();
		$telephone = $this->getRequest()->getParam('telephone');
		$street = $this->getRequest()->getParam('street');
		$city = $this->getRequest()->getParam('city');
		if($middlename != ""){
			$name = $firstname." ".$middlename." ".$lastname;
		} else {
			$name = $firstname." ".$lastname;
		}
		$params = array(
			"CUSTNAME" => $name,
			"CUSTDES"  => "",
			"PHONE"    => $telephone,
			"EMAIL"	   => $email,
			"ADDRESS"  => $street[0],
			"ADDRESS2" => $street[1],
			"STATEA"   => $city 
		);
		$json_request = json_encode($params);
		$request_uri = "https://".$url."/odata/Priority/".$application."/".$enviroment.$additional;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $request_uri);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json_request);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $ssl); 
		$response = curl_exec($curl);
		$info = curl_getinfo($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$http_codes = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			103 => 'Checkpoint',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Switch Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			425 => 'Unordered Collection',
			426 => 'Upgrade Required',
			449 => 'Retry With',
			450 => 'Blocked by Windows Parental Controls',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended'
		);
		if($httpCode == '200' || $httpCode == '201')
		{
			if($httpCode == '201'){
				$status = "Success";
				foreach($http_codes as $key => $value){
					if($key == $httpCode){
						$message = json_encode(array('Code' => $key,'Message' => $value));
						$json_pretty = json_encode(json_decode($message), JSON_PRETTY_PRINT);
					}
				}
			} else {
				$status = "Success";
				header('Content-Type: application/json');
				$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
			}
		} else {
			$status = "Failed";
			foreach($http_codes as $key => $value){
				if($key == $httpCode){
					$message = json_encode(array('Code' => $key,'Error' => $value));
					$json_pretty = json_encode(json_decode($message), JSON_PRETTY_PRINT);
				}
			}
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
        return parent::execute();
    }
}