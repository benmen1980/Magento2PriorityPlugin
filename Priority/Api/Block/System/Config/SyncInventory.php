<?php

namespace Priority\Api\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SyncInventory extends Field
{
	
    protected $_template = 'Priority_Api::system/config/inventory.phtml';

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
    public function getAjaxUrl()
    {
        return $this->getUrl('priorityapi/system_config/inventory');
    }
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'inventory_id',
                'label' => __('Sync Inventory Availability From API'),
            ]
        );
        return $button->toHtml();
    }
}