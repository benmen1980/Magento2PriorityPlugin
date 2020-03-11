<?php 
namespace Priority\Api\Controller\Account;

use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Helper\Address;
use Magento\Framework\UrlFactory;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Customer\Model\Registration;
use Magento\Framework\Escaper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Controller\AbstractAccount;

class CreatePost extends \Magento\Customer\Controller\AbstractAccount
{

    protected $accountManagement;

    protected $addressHelper;

    protected $formFactory;

    protected $subscriberFactory;

    protected $regionDataFactory;

    protected $addressDataFactory;

    protected $registration;

    protected $customerDataFactory;

    protected $customerUrl;

    protected $escaper;

    protected $customerExtractor;

    protected $urlModel;

    protected $dataObjectHelper;

    protected $session;

    private $accountRedirect;

    private $cookieMetadataFactory;

    private $cookieMetadataManager;

    private $formKeyValidator;

    private $customerRepository;

    public function __construct(
        Context $context,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManagement,
        Address $addressHelper,
        UrlFactory $urlFactory,
        FormFactory $formFactory,
        SubscriberFactory $subscriberFactory,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerUrl $customerUrl,
        Registration $registration,
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        AccountRedirect $accountRedirect,
        CustomerRepository $customerRepository,
        Validator $formKeyValidator = null,
		\Priority\Api\Model\TransactionsFactory  $transaction
    ) {
		$this->session = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->accountManagement = $accountManagement;
        $this->addressHelper = $addressHelper;
        $this->formFactory = $formFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->regionDataFactory = $regionDataFactory;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerDataFactory = $customerDataFactory;
        $this->customerUrl = $customerUrl;
        $this->registration = $registration;
        $this->escaper = $escaper;
        $this->customerExtractor = $customerExtractor;
        $this->urlModel = $urlFactory->create();
        $this->dataObjectHelper = $dataObjectHelper;
        $this->accountRedirect = $accountRedirect;
        $this->formKeyValidator = $formKeyValidator ?: ObjectManager::getInstance()->get(Validator::class);
        $this->customerRepository = $customerRepository;
		$this->_transactions = $transaction;
        parent::__construct($context);
    }
	
	public function execute() 
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
		$firstname = $this->getRequest()->getParam('firstname');
		$lastname = $this->getRequest()->getParam('lastname');
		$middlename = $this->getRequest()->getParam('middlename');
		$email = $this->getRequest()->getParam('email');
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