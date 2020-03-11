<?php
namespace Priority\Api\Model\Config;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_PATH_ITEM = 'crontab/default/jobs/priority_api_sync_item_cron/schedule/cron_expr';

    const CRON_MODEL_PATH_ITEM = 'crontab/default/jobs/priority_api_sync_item_cron/run/model';

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
		
        $timeItem = $this->getData('groups/configurable_cron_syncitem/fields/itemtime/value');
        $frequencyItem = $this->getData('groups/configurable_cron_syncitem/fields/itemfrequency/value');
		if($timeItem[1] == '00' || $timeItem[1] == '01'){
			$minutes = "*";
		} else {
			$minutes = "*/".intval($timeItem[1]);
		}
		if($timeItem[0] == '00'){
			$hours = "*";
		} else {
			$hours = intval($timeItem[0]);
		}
        $cronExprItem = [
            $minutes, //Minutes
            $hours, //hour
            $frequencyItem == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
            '*', //Month of the Year
            $frequencyItem == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*', //Day of the Week
        ];
        $cronExprString = join(' ', $cronExprItem);

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