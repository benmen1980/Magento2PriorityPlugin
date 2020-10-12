<?php

namespace Priority\Api\Plugin;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\ObjectManagerInterface;

class PluginBtnOrderView
{
    protected $object_manager;
    protected $_backendUrl;

    public function __construct(
        ObjectManagerInterface $om,
        UrlInterface $backendUrl
    ) {
        $this->object_manager = $om;
        $this->_backendUrl = $backendUrl;
    }

    public function beforeSetLayout( \Magento\Sales\Block\Adminhtml\Order\View $subject )
    {
        $sendOrder = $this->_backendUrl->getUrl('priorityapi/order/repost/');
        $subject->addButton(
            'sendordersms',
            [
                'label' => __('Repost'),
                'onclick' => "setLocation('" . $sendOrder. "')",
                'class' => 'ship primary'
            ]
        );

        return null;
    }

}