<?php
namespace Priority\Api\Controller\Adminhtml\System\Config;

use Priority\Api\Model\TransactionsFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Context;

class Syncitem extends \Magento\Backend\App\Action
{
    protected $_logger;
	
	protected $_scopeConfig;
	
	protected $resultJsonFactory;
	
	protected $_transaction;
	
    protected $resultRedirect;	
	
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Priority\Api\Model\TransactionsFactory  $transaction,
		\Magento\Framework\Controller\ResultFactory $result,
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		\Magento\Catalog\Model\Product $product,
		\Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        parent::__construct($context);
		$this->_scopeConfig = $scopeConfig;
		$this->_logger = $logger;
		$this->_transactions = $transaction;
        $this->resultRedirect = $result;
		$this->_messageManager = $messageManager;
		$this->product = $product;
		$this->productFactory = $productFactory;
	
    }
    public function execute()
    {
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
		$cronset = $this->_scopeConfig->getValue("general_settings/configurable_cron_syncitem/itemtime", $storeScope);
		if($cronset != "0"){
			$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
			$objDate = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');


			$username = $this->_scopeConfig->getValue("settings/general/username", $storeScope);
			$password = $this->_scopeConfig->getValue("settings/general/password", $storeScope);
			$application = $this->_scopeConfig->getValue("settings/general/application", $storeScope);  
			$enviroment = $this->_scopeConfig->getValue("settings/general/environment_name", $storeScope); 
			$language = $this->_scopeConfig->getValue("settings/general/language", $storeScope);
			$url = $this->_scopeConfig->getValue("settings/general/url", $storeScope);
			$ssl_verify = $this->_scopeConfig->getValue("settings/general/ssl_verify", $storeScope);
			$log = $this->_scopeConfig->getValue("general_settings/configurable_cron_syncitem/itemsynclog",$storeScope);
			$appId = $this->_scopeConfig->getValue("settings/general/app_id",$storeScope);
			$appKey = $this->_scopeConfig->getValue("settings/general/app_key",$storeScope);
			if($ssl_verify == 1){
				$ssl = 'TRUE';
			} else {
				$ssl = 'FALSE';
			}		
			
			//$additional = "/LOGPART?$"."select=PARTNAME,SPEC1,SPEC2,SPEC3,SPEC4,FAMILYNAME,PNCO_CONTENT,PNCO_ISKOSHER,ROYY_EXPYEAR,PNCO_SHOWINWEB";
			$additional = "/LOGPART";
			
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
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $ssl);
			$response = curl_exec($curl);
			$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
			curl_close($curl);
			$responsedata = json_decode($response,true);
			foreach($responsedata['value'] as $rdata){
				$sku = $rdata['PARTNAME'];
				$name = $rdata['PARTDES'];
				$price = $rdata['VATPRICE'];
				$urlkey = $name."-".$sku;
				$alcoholpercent = $rdata['SPEC1'];
				$alcoholtype = $rdata['FAMILYNAME'];
				$brand = $rdata['SPEC2'];
				$capacity = $rdata['PNCO_CONTENT'];
				$country = $rdata['SPEC3'];
				$kosher = $rdata['PNCO_ISKOSHER'];
				$wine_type = $rdata['SPEC4'];
				$year = $rdata['ROYY_EXPYEAR'];
				$status = $rdata['PNCO_SHOWINWEB'];
				if($status == 'Y')
				{
					$st = 4;
				} else {
					$st = 1;
				}
				$product = $objectManager->create('\Magento\Catalog\Model\Product');
				if(!$product->getIdBySku($sku)){
					$alcoholp = $product->getResource()->getAttribute('alcohol_percent');
					$alcoholpId = '';
					if ($alcoholp && $alcoholp->usesSource()) {
						$alcoholpId = $alcoholp->getSource()->getOptionId($alcoholpercent);
					}
					$alcoholt = $product->getResource()->getAttribute('alcohol_type');
					$alcoholtId = '';
					if ($alcoholt && $alcoholt->usesSource()) {
						$alcoholtId = $alcoholt->getSource()->getOptionId($alcoholtype);
					}
					$brand_ar = $product->getResource()->getAttribute('brand');
					$brandId = '';
					if ($brand_ar && $brand_ar->usesSource()) {
						$brandId = $brand_ar->getSource()->getOptionId($brand);
					}
					$capacity_ar = $product->getResource()->getAttribute('capacity');
					$capacityId = '';
					if ($capacity_ar && $capacity_ar->usesSource()) {
						$capacityId = $capacity_ar->getSource()->getOptionId($capacity);
					}
					$country_ar = $product->getResource()->getAttribute('country');
					$countryId = '';
					if ($country_ar && $country_ar->usesSource()) {
						$countryId = $country_ar->getSource()->getOptionId($country);
					}
					$kosher_ar = $product->getResource()->getAttribute('kosher');
					$kosherId = '';
					if ($kosher_ar && $kosher_ar->usesSource()) {
						$kosherId = $kosher_ar->getSource()->getOptionId($kosher);
					}
					$wine_type_ar = $product->getResource()->getAttribute('wine_type');
					$wine_typeId = '';
					if ($wine_type_ar && $wine_type_ar->usesSource()) {
						$wine_typeId = $wine_type_ar->getSource()->getOptionId($wine_type);
					}
					$year_ar = $product->getResource()->getAttribute('Year');
					$yearId = '';
					if ($year_ar && $year_ar->usesSource()) {
						$yearId = $year_ar->getSource()->getOptionId($year);
					}
					$product->setSku($sku); 
					$product->setName($name); 
					$product->setAttributeSetId(4); 
					$product->setStatus(1); 
					$product->setWeight(10); 
					$product->setVisibility($st);
					$product->setTypeId('simple'); 
					$product->setPrice($price);
					$product->setWebsiteIds(array(1));
					$product->setUrlKey($urlkey);
					$product->setAlcoholPercent($alcoholpId);
					$product->setAlcoholType($alcoholtId);
					$product->setBrand($brandId);
					$product->setCapacity($capacityId);
					$product->setCountry($countryId);
					$product->setKosher($kosherId);
					$product->setWineType($wine_typeId);
					$product->setYear($yearId);
					$product->setStockData(
							array(
								'use_config_manage_stock' => 0,
								'manage_stock' => 1,
								'is_in_stock' => 1,
								'qty' => 0
							)
						);
					$product->save();
				} else {
					$existproduct = $this->productFactory->create();
					$alcoholp = $existproduct->getResource()->getAttribute('alcohol_percent');
					$alcoholpId = '';
					if ($alcoholp && $alcoholp->usesSource()) {
						$alcoholpId = $alcoholp->getSource()->getOptionId($alcoholpercent);
					}
					$alcoholt = $existproduct->getResource()->getAttribute('alcohol_type');
					$alcoholtId = '';
					if ($alcoholt && $alcoholt->usesSource()) {
						$alcoholtId = $alcoholt->getSource()->getOptionId($alcoholtype);
					}
					$brand_ar = $existproduct->getResource()->getAttribute('brand');
					$brandId = '';
					if ($brand_ar && $brand_ar->usesSource()) {
						$brandId = $brand_ar->getSource()->getOptionId($brand);
					}
					$capacity_ar = $existproduct->getResource()->getAttribute('capacity');
					$capacityId = '';
					if ($capacity_ar && $capacity_ar->usesSource()) {
						$capacityId = $capacity_ar->getSource()->getOptionId($capacity);
					}
					$country_ar = $existproduct->getResource()->getAttribute('country');
					$countryId = '';
					if ($country_ar && $country_ar->usesSource()) {
						$countryId = $country_ar->getSource()->getOptionId($country);
					}
					$kosher_ar = $existproduct->getResource()->getAttribute('kosher');
					$kosherId = '';
					if ($kosher_ar && $kosher_ar->usesSource()) {
						$kosherId = $kosher_ar->getSource()->getOptionId($kosher);
					}
					$wine_type_ar = $existproduct->getResource()->getAttribute('wine_type');
					$wine_typeId = '';
					if ($wine_type_ar && $wine_type_ar->usesSource()) {
						$wine_typeId = $wine_type_ar->getSource()->getOptionId($wine_type);
					}
					$year_ar = $existproduct->getResource()->getAttribute('Year');
					$yearId = '';
					if ($year_ar && $year_ar->usesSource()) {
						$yearId = $year_ar->getSource()->getOptionId($year);
					}
					$existproduct->load($existproduct->getIdBySku($sku));
					$existproduct->setName($name); 
					$existproduct->setPrice($price);
					$existproduct->setStatus(1); 
					$existproduct->setVisibility($st);
					$existproduct->setWeight(10); 
					$existproduct->setPrice($price);
					$existproduct->setWebsiteIds(array(1));
					$existproduct->setUrlKey($urlkey);
					$existproduct->setAlcoholPercent($alcoholpId);
					$existproduct->setAlcoholType($alcoholtId);
					$existproduct->setBrand($brandId);
					$existproduct->setCapacity($capacityId);
					$existproduct->setCountry($countryId);
					$existproduct->setKosher($kosherId);
					$existproduct->setWineType($wine_typeId);
					$existproduct->setYear($yearId);
					$existproduct->setStockData(
							array(
								'use_config_manage_stock' => 0,
								'manage_stock' => 1,
								'is_in_stock' => 1,
								'qty' => 0
							)
						);
					$existproduct->save();
				}
			}
			if($httpCode == '200')
			{
				$status = "Success";
				$json_pretty = json_encode(json_decode($response), JSON_PRETTY_PRINT);
				$this->_messageManager->addSuccess('API Items Sync Successfully.');
				echo "Test:-".$json_pretty;
			} else {
				$status = "Failed";
				$json_pretty = $response;
				$this->_messageManager->addError('Something Wrong');
			}	
			if($log == 1){
				$model = $this->_transactions->create();
				$model->addData([
					"url" => $request_uri,
					"request_method" => "GET",
					"json_request" => "",
					"json_response" => $json_pretty,
					"status" => $status,
					"transaction_date" => $objDate->gmtDate()
					]);
				 $model->save();
			}
		}
	}
}