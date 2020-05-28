<?php
namespace Priority\Api\Model\Config;

class CronConfigOrder extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_PATH_ITEM = 'crontab/default/jobs/priority_api_sync_order_cron/schedule/cron_expr';

    const CRON_MODEL_PATH_ITEM = 'crontab/default/jobs/priority_api_sync_order_cron/run/model';

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
		
        // $orderItem = $this->getData('groups/configurable_cron_syncorder/fields/ordertime/value');
        // $frequencyOrder = $this->getData('groups/configurable_cron_syncorder/fields/orderfrequency/value');
		// if($orderItem[1] == '00' || $orderItem[1] == '01'){
			// $minutes = "*";
		// } else {
			// $minutes = "*/".intval($orderItem[1]);
		// }
		// if($orderItem[0] == '00'){
			// $hours = "*";
		// } else {
			// $hours = intval($orderItem[0]);
		// }
        // $cronExprItem = [
            // $minutes, //Minutes
            // $hours, //hour
            // $frequencyOrder == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
            // '*', //Month of the Year
            // $frequencyOrder == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*', //Day of the Week
        // ];
        // $cronExprString = join(' ', $cronExprItem);
		
		$orderItem = $this->getData('groups/configurable_cron_syncorder/fields/ordertime/value');
        $frequencyOrder = $this->getData('groups/configurable_cron_syncorder/fields/orderfrequency/value');
        
		if($frequencyOrder == 'M')
		{
			if($orderItem!="0")
			{
				$minutes="*/".$orderItem." * * * *";
			}
			else
			{
				$minutes="* * * * *";
			}
			
			$cronExprString=$minutes;
		}

		if($frequencyOrder == 'H')
		{
			if($orderItem!="0")
			{
				$hours="0 */".$orderItem." * * * *";
			}
			else
			{
				$hours="0 * * * *";
			}
			$cronExprString=$hours;
		}
        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH_ITEM,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH_ITEM
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH_ITEM,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH_ITEM
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}