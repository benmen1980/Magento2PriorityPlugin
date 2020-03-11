<?php
 
namespace Priority\Api\Observer;
 
use Magento\Framework\Event\ObserverInterface;
 
class PlaceOrder implements ObserverInterface
{
	
	protected $_transportBuilder;

	protected $inlineTranslation;
 
	protected $scopeConfig;
	
	protected $storeManager;
	
	protected $_escaper;
  
    protected $orderModel;
 
    protected $checkoutSession;
 
    public function __construct(
        \Magento\Sales\Model\OrderFactory $orderModel,
        \Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
		\Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
		\Magento\Framework\Escaper $escaper
    )
    {
        $this->orderModel = $orderModel;
        $this->checkoutSession = $checkoutSession;
		$this->scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->_transactions = $transaction;
		$this->_transportBuilder = $transportBuilder;
		$this->inlineTranslation = $inlineTranslation;
		$this->_escaper = $escaper;
    }
 
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$cronset = $this->scopeConfig->getValue("general_settings/configurable_cron_syncorder/ordertime", $storeScope);
		if($cronset == "00,00,00"){
			$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
			$objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
			
			$orderIds = $observer->getEvent()->getOrderIds();
			if(count($orderIds))
			{
				$order = $this->orderModel->create()->load($orderIds[0]);
		
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
				}
				//echo "<pre>";
				//print_r($orderitems);exit;
				
				$username = $this->scopeConfig->getValue("settings/general/username", $storeScope);
				$password = $this->scopeConfig->getValue("settings/general/password", $storeScope);
				$application = $this->scopeConfig->getValue("settings/general/application", $storeScope);  
				$enviroment = $this->scopeConfig->getValue("settings/general/environment_name", $storeScope); 
				$language = $this->scopeConfig->getValue("settings/general/language", $storeScope);
				$url = $this->scopeConfig->getValue("settings/general/url", $storeScope);
				$ssl_verify = $this->scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
				$ship = $this->scopeConfig->getValue("general_settings/general_config/sku_shippment_item", $storeScope);
				$shipcharge = array(
					"PARTNAME" => $ship,
					"TQUANT" => 1,
					"VPRICE" => floatval($order->getShippingAmount())		
				);
				array_push($orderitem,$shipcharge);
				if($ssl_verify == 1){
					$ssl = 'TRUE';
				} else {
					$ssl = 'FALSE';
				}	
				$additional = "/ORDERS";
				$params = array(
					"CUSTNAME" => $customerid,
					"CURDATE"  => date("Y-m-d"),
					"BOOKNUM"  => $orderid,
					"ORDERITEMS_SUBFORM" => $orderitem,
					"DETAILS"  => $orderid
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
					$name = $this->scopeConfig->getValue("trans_email/ident_general/name", $storeScope);  
					$email = $this->scopeConfig->getValue("trans_email/ident_general/email", $storeScope);  
					$recipient = $this->scopeConfig->getValue("general_settings/more_settings_config/mailing_list", $storeScope); 
					$recipients = explode(",",$recipient);
					//echo "<pre>";
					//print_R($recipients);exit;
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
				}
				$json_request = json_encode(json_decode($json_request), JSON_PRETTY_PRINT);
				$model = $this->_transactions->create();
				$model->addData([
					"url" => $request_uri,
					"request_method" => 'POST',
					"json_request" => $json_request,
					"json_response" => $json_pretty,
					"status" => $status,
					"transaction_date" => $objDate->gmtDate(),
					"order_increment_id"=>$orderid
					]);
				$saveData = $model->save();
			}
		}
    }
}