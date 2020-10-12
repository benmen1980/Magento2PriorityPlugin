<?php
namespace Priority\Api\Model\Config;

class CronConfigInventory extends \Magento\Framework\App\Config\Value
{
	const CRON_STRING_PATH_INVENTORY = 'crontab/default/jobs/priority_api_sync_inventory_cron/schedule/cron_expr';

    const CRON_MODEL_PATH_INVENTORY = 'crontab/default/jobs/priority_api_sync_inventory_cron/run/model';

    protected $_configValueFactory;

    protected $_runModelPath = '';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

   
    public function afterSave()
    {
		
		// $timeInventory = $this->getData('groups/configurable_cron_syncinventory/fields/inventorytime/value');
        // $frequencyInventory = $this->getData('groups/configurable_cron_syncinventory/fields/inventoryfrequency/value');
		 // $frequencyItem = $this->getData('groups/configurable_cron_syncitem/fields/itemfrequency/value');
		// if($timeInventory[1] == '00' || $timeInventory[1] == '01'){
			// $minutes = "*";
		// } else {
			// $minutes = "*/".intval($timeInventory[1]);
		// }
		// if($timeInventory[0] == '00'){
			// $hours = "*";
		// } else {
			// $hours = intval($timeInventory[0]); 
		// }
        // $cronExprInventory = [
            // $minutes, 
            // $hours, 
            // $frequencyInventory == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
            // '*', //Month of the Year
            // $frequencyInventory == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*', //Day of the Week
        // ];

        // $cronExprStringInventory = join(' ', $cronExprInventory);
		
		$timeInventory = $this->getData('groups/configurable_cron_syncinventory/fields/inventorytime/value');
        $frequencyInventory = $this->getData('groups/configurable_cron_syncinventory/fields/inventoryfrequency/value');
        
		if($frequencyInventory == 'M')
		{
			if($timeInventory!="0")
			{
				$minutes="*/".$timeInventory." * * * *";
			}
			else
			{
				$minutes="* * * * *";
			}
			
			$cronExprStringInventory=$minutes;
		}

		if($frequencyInventory == 'H')
		{
			if($timeInventory!="0")
			{
				$hours="0 */".$timeInventory." * * * *";
			}
			else
			{
				$hours="0 * * * *";
			}
			$cronExprStringInventory=$hours;
		}

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH_INVENTORY,
                'path'
            )->setValue(
                $cronExprStringInventory
            )->setPath(
                self::CRON_STRING_PATH_INVENTORY
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH_INVENTORY,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH_INVENTORY
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}