<?php 
namespace Priority\Api\Observer;

use Magento\Framework\Event\ObserverInterface;
 
class ConfigChange implements ObserverInterface
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
		$postData = $this->_request->getParam('groups');
        $ordervalue = $postData['configurable_cron_syncorder']['fields']['ordertime']['value'];
		$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
		$connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection('\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION');
		if($ordervalue == 0){
			$sql = "Delete FROM core_config_data Where path like '%crontab/default/jobs/priority_api_sync_order_cron/schedule/cron_expr%'";
			$connection->query($sql);
		}
    }
}