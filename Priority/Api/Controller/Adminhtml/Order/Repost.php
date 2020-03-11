<?php
namespace Priority\Api\Controller\Adminhtml\Order;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class Repost extends \Magento\Backend\App\Action
{

    protected $connector;

    protected $_transportBuilder;

    protected $inlineTranslation;

    protected $scopeConfig;

    protected $storeManager;

    protected $_escaper;

    public function __construct(
		\Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Priority\Api\Model\TransactionsFactory $transaction,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Escaper $escaper,
		\Magento\Sales\Api\Data\OrderInterface $order,
		\Magento\Framework\App\Request\Http $request
    ) {
		parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->_transactions = $transaction;
        $this->_transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->_escaper = $escaper;
		$this->order = $order;
		$this->request = $request;
    }

    public function execute() {
		try{
			$id = $this->request->getParam('id');
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$collection = $objectManager->create('Priority\Api\Model\ResourceModel\Transactions\Collection')->addFieldToFilter('order_increment_id',$id);
			foreach($collection as $orderdata){
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
				
				$headers = array('Content-Type: application/json');
				if($ssl_verify == 1){
					$ssl = 'TRUE';
				} else {
					$ssl = 'FALSE';
				}
				$additional = "/ORDERS";
				
				$json_request = $orderdata->getJsonRequest();
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
					$this->messageManager->addSuccess(__('Successfully created order in API'));
				} else {
					$status = "Failed";
					$json_pretty = $response;
					$name = $this->scopeConfig->getValue("trans_email/ident_general/name", $storeScope);
					$email = $this->scopeConfig->getValue("trans_email/ident_general/email", $storeScope);
					$recipient = $this->scopeConfig->getValue("general_settings/more_settings_config/mailing_list", $storeScope);
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
					}
					$this->messageManager->addError(__('Please try to repost after product sync.'));
				}
				$json_request = json_encode(json_decode($json_request), JSON_PRETTY_PRINT);
				
				$model = $this->_transactions->create();
				if($orderdata['status'] == "Failed"){
					$postUpdate = $model->load($orderdata->getId());
					$postUpdate->setJsonRequest($json_request);
					$postUpdate->setJsonResponse($json_pretty);
					$postUpdate->setStatus($status);
					$postUpdate->save();
				}
				$model->addData([
					"url" => $request_uri,
					"request_method" => 'POST',
					"json_request" => $json_request,
					"json_response" => $json_pretty,
					"status" => $status,
					"transaction_date" => $objDate->gmtDate(),
					"order_increment_id"=> $orderdata->getIncrementId()
					]);
				$saveData = $model->save();
			}
		}catch (\Exception $e) {
			$this->messageManager->addError($e->getMessage());            
		}
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setRefererOrBaseUrl();
		return $resultRedirect;
    }
}