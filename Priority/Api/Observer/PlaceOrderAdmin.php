<?php
namespace Priority\Api\Observer;

use Magento\Framework\Event\ObserverInterface;

class PlaceOrderAdmin implements ObserverInterface {
	
    protected $connector; 
	
	protected $_transportBuilder;

	protected $inlineTranslation;
 
	protected $scopeConfig;
	
	protected $storeManager;
	
	protected $_escaper;
	
	protected $customerFactory;
	
    protected $addressFactory;
	
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Priority\Api\Model\TransactionsFactory $transaction,
		\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
		\Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
		\Magento\Framework\Escaper $escaper,
		\Wyomind\AdvancedInventory\Model\StockRepository $stockRepository,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezoneInterface,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory
		) { 
		$this->scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->_transactions = $transaction;
		$this->_transportBuilder = $transportBuilder;
		$this->inlineTranslation = $inlineTranslation;
		$this->_escaper = $escaper;
		$this->_stockrepository = $stockRepository;
		$this->_timezoneInterface = $timezoneInterface;
		$this->_customerFactory = $customerFactory;
        $this->_addressFactory = $addressFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) 
	{ 
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$cronset = $this->scopeConfig->getValue("general_settings/configurable_cron_syncorder/ordertime", $storeScope);
		if($cronset == "0"){
			$order = $observer->getEvent()->getOrder();
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
					"CCUID" => $order->getPayment()->getAdditionalInformation('card_id'),
					"CONFNUM" => $order->getCcTransId(),
					"BIC" => $order->getPayment()->getCcType()
				);
			} else {
				$paymentarray = array("PAYMENTCODE" => $paymentcode,"QPRICE" => (float)$order->getGrandTotal());
			}
			$customerid = $order->getCustomerId();
			$orderid = $order->getIncrementId();
			$orderItems = $order->getAllItems();
			$orderitem = array();
			foreach ($order->getAllItems() as $item) {
				$items['PARTNAME'] = $item->getSku();
				$items['TQUANT'] = (int)$item->getQtyOrdered();
				$items['VPRICE'] = floatval($item->getPrice());
				array_push($orderitem,$items);
			}
			
			$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
			$objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
			$username = $this->scopeConfig->getValue("settings/general/username", $storeScope);
			$password = $this->scopeConfig->getValue("settings/general/password", $storeScope);
			$application = $this->scopeConfig->getValue("settings/general/application", $storeScope);  
			$enviroment = $this->scopeConfig->getValue("settings/general/environment_name", $storeScope); 
			$language = $this->scopeConfig->getValue("settings/general/language", $storeScope);
			$url = $this->scopeConfig->getValue("settings/general/url", $storeScope);
			$ssl_verify = $this->scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
			$ship = $this->scopeConfig->getValue("general_settings/general_config/sku_shippment_item", $storeScope);
			$appId = $this->scopeConfig->getValue("settings/general/app_id",$storeScope);
			$appKey = $this->scopeConfig->getValue("settings/general/app_key",$storeScope); 
			$connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection('\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION');
			$warehouses = $this->_stockrepository->getAssignationByOrderId($order->getId());
			$warehouse_data = json_decode($warehouses,true);
			if($order->getStoreId() == 3){
				$place_id = 4;
			} else {
				if(!empty($warehouse_data)){
					$place_id = $warehouse_data[0]['place_id'];
				} else {
					$place_id = "";
				}
			}
			$giftsql="select sum(gift_amount) as total from amasty_amgiftcard_quote aaq where aaq.quote_id = (select so.quote_id from sales_order so where so.entity_id=".$order->getId().")";
			$giftresult = $connection->fetchAll($giftsql);
			if(!empty($giftresult[0]['total'])){
				$giftdsicount = array(
					"PARTNAME" => "7001",
					"TQUANT" => -1,
					"VPRICE" => (float)$giftresult[0]['total']	
				);
				array_push($orderitem,$giftdsicount);
			}
			$scsql="select amstorecredit_amount from  sales_order where entity_id=".$order->getId();
				$scresult = $connection->fetchAll($scsql);
				if(!empty($scresult[0]['amstorecredit_amount'])){
					$scdsicount = array(
						"PARTNAME" => "7003",
						"TQUANT" => -1,
						"VPRICE" => (float)$scresult[0]['amstorecredit_amount']	
					);
					array_push($orderitem,$scdsicount);
				}
				$rewardsql="select order_id, spend_points from mst_rewards_purchase where order_id=".$order->getId();
				$rewardresult = $connection->fetchAll($rewardsql);
				if($rewardresult[0]['spend_points'] != 0){
					$rpdsicount = array(
						"PARTNAME" => "7017",
						"TQUANT" => -1,
						"VPRICE" => (float)$rewardresult[0]['spend_points']	
					);
					array_push($orderitem,$rpdsicount);
				}
				$dmsql="select discount_amount from sales_order so where so.entity_id =".$order->getId();
				$dmresult = $connection->fetchAll($dmsql);
				if($dmresult[0]['discount_amount'] != '0.0000'){
					$dsicount = array(
						"PARTNAME" => "7018",
						"TQUANT" => -1,
						"VPRICE" => abs((float)$dmresult[0]['discount_amount'])
					);
					array_push($orderitem,$dsicount);
				}
				$giftwrapsql="select w.order_id, w.card_id, w.wrap_id, w.gift_message, w.price, m.sort_order as card_sort, r.sort_order as wrap_sort from amasty_giftwrap_order_wrap w inner join amasty_giftwrap_message_card_store m on w.card_id = m.message_card_id inner join amasty_giftwrap_wrap_store r on r.wrap_id = w.wrap_id where order_id = ".$order->getId();
				$giftwrapresult = $connection->fetchAll($giftwrapsql);
				if(!empty($giftwrapresult)){
					$giftwrapcardid = array(
						"PARTNAME" => $giftwrapresult[0]['card_sort'],
						"TQUANT" => 1,
						"VPRICE" => 0
					);
					array_push($orderitem,$giftwrapcardid);
					$giftwrapid = array(
						"PARTNAME" => $giftwrapresult[0]['wrap_sort'],
						"TQUANT" => 1,
						"VPRICE" => abs((float)$giftwrapresult[0]['price'])
					);
					array_push($orderitem,$giftwrapid);		
				}
			$shipcharge = array(
				"PARTNAME" => $ship,
				"TQUANT" => 1,
				"VPRICE" => (float)$order->getShippingAmount()		
			);
			array_push($orderitem,$shipcharge);
			$housesql="select house_number from sales_order_address where entity_id = (select shipping_address_id from sales_order where entity_id =".$order->getId().")";
			$houseresult = $connection->fetchAll($housesql);
			if(!empty($houseresult)){
				$house = $houseresult[0]['house_number'];
			} else {
				$house = "";
			}
			$apartmentsql="select apartment from sales_order_address where entity_id = (select shipping_address_id from sales_order where entity_id =".$order->getId().")";
			$apartmentresult = $connection->fetchAll($apartmentsql);
			if(!empty($apartmentresult)){
				$apartment = $apartmentresult[0]['apartment'];
			} else {
				$apartment = "";
			}
			
			$ordergiftmsg=array();
			$giftmsg="select gift_message from amasty_giftwrap_order_wrap where order_id =".$order->getId()." and gift_message <>''";
			$giftmsgresult = $connection->fetchAll($giftmsg);
			if(!empty($giftmsgresult[0]['gift_message'])){
				$giftmsgarr = array(
					"TEXT" => $giftmsgresult[0]['gift_message']
				);
				array_push($ordergiftmsg,$giftmsgarr);
			}
			else{
				$giftmsgarr = array(
					"TEXT" => ""
				);
				array_push($ordergiftmsg,$giftmsgarr);
			}
			
			$giftpackqry="select wrap_id,card_id,gift_message from amasty_giftwrap_order_wrap where order_id =".$order->getId();
			$giftpackresult = $connection->fetchAll($giftpackqry);
			if(!empty($giftpackresult[0]['wrap_id']) || !empty($giftpackresult[0]['card_id']) || !empty($giftpackresult[0]['gift_message'])){
				$pncogiftpack="Y";
			}
			else{
				$pncogiftpack="";
			}
			
			$shipsql="select * from sales_order where entity_id =".$order->getId();
			$shipresult = $connection->fetchAll($shipsql);
			if(!empty($shipresult[0]['shipping_order_comment'])){
				$shipping_order_comment = $shipresult[0]['shipping_order_comment'];
			} else {
				$shipping_order_comment = "";
			}
			if(!empty($shipresult[0]['service_order_comment'])){
				$service_order_comment = $shipresult[0]['service_order_comment'];
			} else {
				$service_order_comment = "";
			}
			if(!empty($shipresult[0]['shipping_package_size_list'])){
				$shipping_package_size_list = $shipresult[0]['shipping_package_size_list'];
			} else {
				$shipping_package_size_list = "";
			}
			if(!empty($shipresult[0]['total_shipping_packages'])){
				$total_shipping_packages = $shipresult[0]['total_shipping_packages'];
			} else {
				$total_shipping_packages = "";
			}
			$date = $this->_timezoneInterface
                                        ->date(new \DateTime($order->getCreatedAt()))
                                        ->format('c');
			$timeslotsql="select additional_information from studioraz_buzzr_shipment where order_id =".$order->getId();
			$timeslotresult = $connection->fetchAll($timeslotsql);
			if(!empty($timeslotresult)){
				$timeslot = json_decode($timeslotresult[0]['additional_information'],true);
				if (isset($timeslot['timeslot_timestart'])){
					$timeslotstartdate = $timeslot['timeslot_timestart'];
					$timestart = date("d/m/Y_Hi", strtotime($timeslotstartdate));
				} else {
					$timestart = "";
				}
				if (isset($timeslot['timeslot_timeend'])){
					$timeslotenddate = $timeslot['timeslot_timeend'];
					$timeend = date("d/m/Y_Hi", strtotime($timeslotenddate));
				} else {
					$timeend = "";
				}
			} else {
				$timestart = "";
				$timeend = "";
			}			
			$shipdetails = array(
				"PNCO_FIRSTNAME" => $order->getShippingAddress()->getFirstName(),
				"PNCO_LASTNAME" => $order->getShippingAddress()->getLastName(),
				"PNCO_STREET" => $order->getShippingAddress()->getStreetLine(1),
				"STATE" => $order->getShippingAddress()->getCity(),  
				"ZIP" => $order->getShippingAddress()->getPostcode(),  
				"PHONENUM" => $order->getShippingAddress()->getTelephone(),
				"PNCO_HOUSENUM" => $house,	
				"PNCO_APPT" => $apartment
			);
			$custname = $order->getCustomerFirstName().' '.$order->getCustomerLastName();
			$headers = array('Content-Type: application/json');
			if($ssl_verify == 1){
				$ssl = 'TRUE';
			} else {
				$ssl = 'FALSE';
			}	
			$additional = "/ORDERS";
			if($order->getCustomerId() == ""){
				$params = array(
					"CUSTNAME" => 'G'.$orderid,
					"CDES" => $custname,
					"CURDATE"  => date("Y-m-d"),
					"BOOKNUM"  => $orderid,
					"PNCO_WEBNUMBER" => $orderid,
					"PNCO_UDATEUDATE" => $date,
					"SHIPREMARK" => $shipping_order_comment,
					"PNCO_REMARKS" => $service_order_comment,
					"ROYY_BUZZERFDT" => $timestart,
					"ROYY_BUZZERTDT" => $timeend,
					"ROYY_PACKAGEVALUE" => (float)$shipresult[0]['shipping_package_value'],
					"ROYY_PACKAGES" => $shipping_package_size_list,
					"PNCO_NUMOFPACKS" => (int)$total_shipping_packages,
					"STCODE"   => $stcode,
					"PNCO_BLESSORDERSTEXT_SUBFORM"=>$ordergiftmsg,
					"ORDERITEMS_SUBFORM" => $orderitem,
					"SHIPTO2_SUBFORM" => $shipdetails,
					"PAYMENTDEF_SUBFORM" => $paymentarray,
					"DETAILS"  => $order->getId(),
					"BRANCHNAME" => (string)$place_id,
					"PNCO_GIFTPACK"=>$pncogiftpack
				);
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
				if($order->getBillingAddress()->getHouseNumber() != "" ){
					$houseno = ',מספר בית:'.$order->getBillingAddress()->getHouseNumber();
				} else {
					$houseno = "";
				}
				if($order->getBillingAddress()->getApartment() != ""){
					$apartment = ',דירה:'.$order->getBillingAddress()->getApartment();
				} else {
					$apartment = "";
				}
				if($order->getBillingAddress()->getFloor() != ""){
					$floor = ',קומה:'.$order->getBillingAddress()->getFloor();
				} else {
					$floor = $street;
				}
				$adddress = $street.$houseno.$apartment.$floor;
				$city = $order->getBillingAddress()->getCity();
				$telephone = $order->getBillingAddress()->getTelephone();
				if($middlename != ""){
					$name = $firstname." ".$middlename." ".$lastname;
				} else {
					$name = $firstname." ".$lastname;
				}
				$params1 = array(
					"CUSTNAME" => 'G'.$orderid,
					"CUSTDES"  => $name,
					"PHONE"    => $telephone,
					"EMAIL"	   => $email,
					"ADDRESS"  => $adddress,
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
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
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
					"request_method" => 'POST',
					"json_request" => $json_request1,
					"json_response" => $json_pretty1,
					"status" => $status1,
					"transaction_date" => $objDate->gmtDate()
					]);
				$saveData1 = $model1->save();
			} else {
				$params = array(
					"CUSTNAME" => $customerid,
					"CURDATE"  => date("Y-m-d"),
					"BOOKNUM"  => $orderid,
					"PNCO_WEBNUMBER" => $orderid,
					"PNCO_UDATEUDATE" => $date,
					"SHIPREMARK" => $shipping_order_comment,
					"PNCO_REMARKS" => $service_order_comment,
					"ROYY_BUZZERFDT" => $timestart,
					"ROYY_BUZZERTDT" => $timeend,
					"ROYY_PACKAGEVALUE" => (float)$shipresult[0]['shipping_package_value'],
					"ROYY_PACKAGES" => $shipping_package_size_list,
					"PNCO_NUMOFPACKS" => (int)$total_shipping_packages,
					"STCODE"   => $stcode,
					"PNCO_BLESSORDERSTEXT_SUBFORM"=>$ordergiftmsg,
					"ORDERITEMS_SUBFORM" => $orderitem,
					"SHIPTO2_SUBFORM" => $shipdetails,
					"PAYMENTDEF_SUBFORM" => $paymentarray,
					"DETAILS"  => $order->getId(),
					"BRANCHNAME" => (string)$place_id,
					"PNCO_GIFTPACK"=>$pncogiftpack
				);
			}
					
			$json_request = json_encode($params);
			$request_uri = "https://".$url."/odata/Priority/".$application.",".$language."/".$enviroment.$additional;
			$ch = curl_init($request_uri);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
			'X-App-Id:'.$appId,
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
						try {
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
						catch (\Exception $e) {
								$this->_logger->debug($e->getMessage());
						}
					}
			}
			$json_request = json_encode(json_decode($json_request), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
			if($order->getCustomerId() != ""){
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
					if($order->getBillingAddress()->getHouseNumber() != "" ){
						$houseno = ',מספר בית:'.$order->getBillingAddress()->getHouseNumber();
					} else {
						$houseno = "";
					}
					if($order->getBillingAddress()->getApartment() != ""){
						$apartment = ',דירה:'.$order->getBillingAddress()->getApartment();
					} else {
						$apartment = "";
					}
					if($order->getBillingAddress()->getFloor() != ""){
						$floor = ',קומה:'.$order->getBillingAddress()->getFloor();
					} else {
						$floor = $street;
					}
					$adddress = $street.$houseno.$apartment.$floor;				
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
						"ADDRESS"  => $adddress,
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