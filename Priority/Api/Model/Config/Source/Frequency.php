<?php
/**
 * Created by PhpStorm.
 * User: mnitin
 * Date: 16-03-2020
 * Time: 12:46
 */

namespace Priority\Api\Model\Config\Source;


class Frequency implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var array
     */
    protected static $_options;

    const CRON_HOURS = 'H';

    const CRON_MINUTES = 'M';

    const CRON_SECONDS = 'S';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = [
                ['label' => __('Hours'), 'value' => self::CRON_HOURS],
                ['label' => __('Minutes'), 'value' => self::CRON_MINUTES],
                ['label' => __('Seconds'), 'value' => self::CRON_SECONDS],
            ];
        }
        return self::$_options;
    }
}