<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\Timezone;

class Order extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $storeManager;
	
	protected $_transaction;
	
	protected $customerFactory;
	
    protected $addressFactory;
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Catalog\Model\Product $product,
		\Magento\Sales\Model\Order $orderModel,
		\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
		\Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
		\Magento\Framework\Escaper $escaper,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory
    ) {
        parent::__construct($context);
		$this->scopeConfig = $scopeConfig;
		$this->_logger = $logger;
		$this->storeManager = $storeManager;
		$this->_transactions = $transaction;
		$this->_product = $product;
		$this->_orderModel = $orderModel;
		$this->_transportBuilder = $transportBuilder;
		$this->inlineTranslation = $inlineTranslation;
		$this->_escaper = $escaper;
		$this->_timezoneInterface = $timezoneInterface;
		$this->_customerFactory = $customerFactory;
        $this->_addressFactory = $addressFactory;
    }
    public function execute()
    {
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$cronset = $this->scopeConfig->getValue("general_settings/configurable_cron_syncorder/ordertime", $storeScope);
		if($cronset != "0"){
			$orders = $this->_orderModel->getCollection();
			//$orders->addFieldToFilter('state', 'new');
			$to = date("Y-m-d h:i:s"); 
			$from = strtotime('-2 day', strtotime($to));
			$from = date('Y-m-d h:i:s', $from); 
			$orders->addFieldToFilter('created_at', array('from'=>$from, 'to'=>$to));
			$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
			$connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection('\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION');
			$objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');	
			$username = $this->scopeConfig->getValue("settings/general/username", $storeScope);
			$password = $this->scopeConfig->getValue("settings/general/password", $storeScope);
			$application = $this->scopeConfig->getValue("settings/general/application", $storeScope);  
			$enviroment = $this->scopeConfig->getValue("settings/general/environment_name", $storeScope); 
			$language = $this->scopeConfig->getValue("settings/general/language", $storeScope);
			$url = $this->scopeConfig->getValue("settings/general/url", $storeScope);
			$ssl_verify = $this->scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
			$ship = $this->scopeConfig->getValue("general_settings/general_config/sku_shippment_item", $storeScope);
			$log = $this->scopeConfig->getValue("general_settings/configurable_cron_syncorder/ordersynclog",$storeScope);
			$appId = $this->scopeConfig->getValue("settings/general/app_id",$storeScope);
			$appKey = $this->scopeConfig->getValue("settings/general/app_key",$storeScope);
			if($ssl_verify == 1){
				$ssl = 'TRUE';
			} else {
				$ssl = 'FALSE';
			}		
			$additional = "/ORDERS";
			$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
			$ordersyncsql = "select DISTINCT order_increment_id from test_unit_transactions where order_increment_id is not null"; 
			$ordersyncresult = $connection->fetchAll($ordersyncsql);
			$order_ids = array();
			foreach($ordersyncresult as $ordersync){
				foreach($ordersync as $key => $value){
					array_push($order_ids,$value);
				}
			}
			foreach($orders as $order){
				$orderid = $order->getIncrementId();
				if(!in_array($orderid,$order_ids)){
					$shipping = $order->getShippingMethod();
					$shipping = explode("_",$shipping);
					$shippigCode = $shipping[0];
					$stcode = $this->scopeConfig->getValue("carriers/".$shippigCode."/priority_code",$storeScope);
					$payment = $order->getPayment()->getMethod();
					$paymentcode = $this->scopeConfig->getValue("payment/".$payment."/priority_code",$storeScope);
					if($payment == "authorizenet_directpost" || $payment == "payflowpro" || $payment == "payflowpro_cc_vault" || $payment == "payflow_link" || $payment == "payflow_advanced" || $payment == "authorizenet_acceptjs" || $payment == "braintree" || $payment == "srcreditguard") {
						$paydes = $order->getPayment()->getAdditionalInformation('installments_number_of_payments') + 1;
						$paymentarray = array(
							"PAYMENTCODE" => $paymentcode, 
							"PAYMENTNAME" => $order->getPayment()->getAdditionalInformation('method_title'),
							"IDNUM" => $order->getPayment()->getCcOwner(),
							"PAYCODE" => (string)$paydes,
							"PAYACCOUNT" => $order->getPayment()->getCcLast4(),
							"VALIDMONTH" => $order->getPayment()->getCcExpMonth().$order->getPayment()->getCcExpYear(),
							"QPRICE" => (float)$order->getGrandTotal(), 
							"CCUID" => $order->getPayment()->getAdditionalInformation('last_trans_id'),
							"CONFNUM" => $order->getCcTransId(),
							"BIC" => $order->getPayment()->getCcType()
						);
					} else {
						$paymentarray = array("PAYMENTCODE" => $paymentcode);
					}
					$status = $order->getState();
					
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
						$items['VATPRICE'] = floatval($item->getRowTotal());
						array_push($orderitem,$items);
					}	
					$shipcharge = array(
							"PARTNAME" => $ship,
							"TQUANT" => 1,
							"VPRICE" => (float)$order->getShippingAmount()	
					);
					array_push($orderitem,$shipcharge);
					$shipdetails = array(
						"FIRSTNAME" => $order->getShippingAddress()->getFirstName(),
						"LASTNAME" => $order->getShippingAddress()->getLastName(),
						"STREET" => $order->getShippingAddress()->getStreetLine(1),
						"STATE" => $order->getShippingAddress()->getCity(),  
						"ZIP" => $order->getShippingAddress()->getPostcode(),  
						"PHONENUM" => $order->getShippingAddress()->getTelephone()
					);
					$params = array(
						"CUSTNAME" => $customerid,
						"CURDATE"  => date("Y-m-d"),
						"BOOKNUM"  => $orderid,
						"STCODE"   => $stcode,
						"ORDERITEMS_SUBFORM" => $orderitem,
						"SHIPTO2_SUBFORM" => $shipdetails,
						"PAYMENTDEF_SUBFORM" => $paymentarray,
						"DETAILS"  => $order->getId()
					);
					$json_request = json_encode($params,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
					$ch = curl_init($request_uri);
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-App-Id:'.$appId,
					'X-App-Key:'.$appKey));
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
					curl_setopt($ch, CURLOPT_TIMEOUT, 30);
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl);
					$response = curl_exec($ch);
					$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
					curl_close($ch);
					if($httpCode == '200' || $httpCode == '201')
					{
						$status = "Success";
						$json_pretty = json_encode(json_decode($response),  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
					} else {
						$status = "Failed";
						if($contentType == "application/json; charset=utf-8"){
							$json_pretty = json_encode(json_decode($response),  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
						} else {
							$json_pretty = $response;
						}
						$recipient = $this->scopeConfig->getValue("general_settings/more_settings_config/mailing_list", $storeScope); 
						
						if(!empty($recipient)){
							$recipients = explode(",",$recipient);
							$name = $this->scopeConfig->getValue("trans_email/ident_general/name", $storeScope);  
							$email = $this->scopeConfig->getValue("trans_email/ident_general/email", $storeScope); 
							foreach($recipients as $key => $to){
								$templateOptions = array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId());
								$templateVars = array(
									'store' => $this->storeManager->getStore()->getName(),
									'order_id' => $orderid,
									'error_code' => $httpCode,
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
					}	
					$json_request = json_encode(json_decode($json_request),  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
					if($log == 1){
						$model = $this->_transactions->create();
						$model->addData([
							"url" => $request_uri,
							"request_method" => "POST",
							"json_request" => $json_request,
							"json_response" => $json_pretty,
							"status" => $status,
							"transaction_date" => $objDate->gmtDate(),
							"order_increment_id" => $orderid
							]);
						$model->save();
					}
					
					$customerBillingStreet = $order->getBillingAddress()->getStreet(); 
					if(count($customerBillingStreet) >= 1){
						$billingstreet = implode(" ",$customerBillingStreet);
					} else {
						$billingstreet = $customerBillingStreet[0];
					}	
					$customerShippingStreet = $order->getShippingAddress()->getStreet(); 
					if(count($customerShippingStreet) >= 1){
						$shippingstreet = implode(" ",$customerShippingStreet);
					} else {
						$shippingstreet = $customerShippingStreet[0];
					}
					$customer = $this->_customerFactory->create()->load($customerid);
					$billingAddressId = $customer->getDefaultBilling();
					if($billingstreet == $shippingstreet || $billingAddressId != $order->getBillingAddressId()){
						$additional1 = "/CUSTOMERS";
						$firstname = $order->getBillingAddress()->getFirstName();
						$lastname = $order->getBillingAddress()->getLastName();
						$middlename = $order->getBillingAddress()->getMiddleName();
						$email = $order->getBillingAddress()->getEmail();
						$customerStreet = $order->getBillingAddress()->getStreet(); 
						if(count($customerStreet) >= 1){
							$street = implode(" ",$customerStreet);
						} else {
							$street = $customerStreet[0];
						}				
						$city = $order->getBillingAddress()->getCity();
						$telephone = $order->getBillingAddress()->getTelephone();
						if($middlename != ""){
							$name = $firstname." ".$middlename." ".$lastname;
						} else {
							$name = $firstname." ".$lastname;
						}
						$params1 = array(
							"CUSTNAME" => $customerid,
							"CUSTDES"  => $name,
							"PHONE"    => $telephone,
							"EMAIL"	   => $email,
							"ADDRESS"  => $street,
							"ADDRESS2" => "",
							"STATEA"   => $city 
						);
						$json_request1 = json_encode($params1,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
						$request_uri1 = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional1;
						$ch = curl_init($request_uri1);
						curl_setopt($ch, CURLOPT_HTTPHEADER, array(
							'Content-Type: application/json',
							'X-App-Id:'.$appId,
							'X-App-Key:'.$appKey
						));
						
						curl_setopt($ch, CURLOPT_HEADER, 0);
						curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
						curl_setopt($ch, CURLOPT_TIMEOUT, 30);
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
						curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request1);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl);
						$response1 = curl_exec($ch);
						$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
						curl_close($ch);
						
						if($httpCode == '200' || $httpCode == '201')
						{
							$status1 = "Success";
							$json_pretty1 = json_encode(json_decode($response1), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
						} else {
							$status1 = "Failed";
							$json_pretty1 = $response1;
						}
						
						$json_request1 = json_encode(json_decode($json_request1),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
						$model1 = $this->_transactions->create();
						$model1->addData([
							"url" => $request_uri1,
							"request_method" => 'PATCH',
							"json_request" => $json_request1,
							"json_response" => $json_pretty1,
							"status" => $status1,
							"transaction_date" => $objDate->gmtDate()
							]);
						$saveData1 = $model1->save();	
					}
				}
			}
		}
	}
}