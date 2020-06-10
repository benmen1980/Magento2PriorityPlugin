<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class Testunit extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $resultJsonFactory;
	
	protected $_transaction;
	
    protected $resultRedirect;	
	
	protected $_transportBuilder;

	protected $inlineTranslation;
	
	protected $_escaper;
	
	protected $storeManager;
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Controller\ResultFactory $result,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
		\Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
		\Magento\Framework\Escaper $escaper
    ) {
        parent::__construct($context);
		$this->_scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->_logger = $logger;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->_transactions = $transaction;
        $this->resultRedirect = $result;
		$this->_transportBuilder = $transportBuilder;
		$this->inlineTranslation = $inlineTranslation;
		$this->_escaper = $escaper;	
    }
    public function execute()
    {
    	$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');

		$resultJson = $this->resultJsonFactory->create();
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$username = $this->_scopeConfig->getValue("settings/general/username", $storeScope);
		$password = $this->_scopeConfig->getValue("settings/general/password", $storeScope);
		$application = $this->_scopeConfig->getValue("settings/general/application", $storeScope);  
		$enviroment = $this->_scopeConfig->getValue("settings/general/environment_name", $storeScope); 
		$language = $this->_scopeConfig->getValue("settings/general/language", $storeScope);
		$url = $this->_scopeConfig->getValue("settings/general/url", $storeScope);
		$ssl_verify = $this->_scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
		$appId = $this->_scopeConfig->getValue("settings/general/app_id",$storeScope);
		$appKey = $this->_scopeConfig->getValue("settings/general/app_key",$storeScope);
		if($ssl_verify == 1){
			$ssl = 'TRUE';
		} else {
			$ssl = 'FALSE';
		}		
		
		$additional = $this->getRequest()->getPostValue('additional');
		$action = $this->getRequest()->getPostValue('action');
		$json_request = $this->getRequest()->getPostValue('body');
		
		if($this->getRequest()->getPostValue('additional') != null){
			$additional = "/".$this->getRequest()->getPostValue('additional');
			$additional = str_replace(' ', '%20', $additional);
		} else {
			$additional = "/";
		}
		if($action == "post"){
			$method = "POST";
		} else if($action == "patch"){
			$method = "PATCH";
		} else if($action == "delete"){
			$method = "DELETE";
		} else {
			$method = "GET";
		}
		
		$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
		$curl = curl_init($request_uri);
		curl_setopt($curl, CURLOPT_URL, $request_uri);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'X-App-Id:'.$appId,
			'X-App-Key:'.$appKey));
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_USERPWD, $username . ":" . $password);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		if($action != "get"){
			curl_setopt($curl, CURLOPT_POSTFIELDS, $json_request);
		}
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $ssl); 
		$response = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
		curl_close($curl);
		
		if($httpCode == '200' || $httpCode == '201')
		{
			$status = "Success";
			$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
			
		} else {
			$status = "Failed";
			//	$json_pretty = $response;
			$json_pretty=json_encode(json_decode($response),JSON_PRETTY_PRINT);// To print response errors like 401,404,503 etc
			
			/*$name = $this->_scopeConfig->getValue("trans_email/ident_general/name", $storeScope);  
			$email = $this->_scopeConfig->getValue("trans_email/ident_general/email", $storeScope);  
			$recipient = $this->_scopeConfig->getValue("general_settings/more_settings_config/mailing_list", $storeScope); 
			$recipients = explode(",",$recipient);
			foreach($recipients as $key => $to){
				$templateOptions = array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId());
				$templateVars = array(
									'request_url' => $request_uri,
									'api_error'	=> $json_pretty
								);
				$from = array('email' => $this->_escaper->escapeHtml($email), 'name' => $this->_escaper->escapeHtml($name));
				$this->inlineTranslation->suspend();
				$transport = $this->_transportBuilder->setTemplateIdentifier('api_template')
								->setTemplateOptions($templateOptions)
								->setTemplateVars($templateVars)
								->setFrom($from)
								->addTo($to)
								->getTransport();
				$transport->sendMessage();
				$this->inlineTranslation->resume();
			}*/
		}	
		$resultJson->setData($response);
		$json_request = json_encode(json_decode($json_request), JSON_PRETTY_PRINT);
		$model = $this->_transactions->create();
		$model->addData([
			"url" => $request_uri,
			"request_method" => $method,
			"json_request" => $json_request,
			"json_response" => $json_pretty,
			"status" => $status,
			"transaction_date" => $objDate->gmtDate()
			]);
		$model->save();
		if($contentType == "text/plain; charset=utf-8"){
			$response = json_encode(trim(strip_tags($response)));
		} 
		return $resultJson;
	}
}