<?php
namespace Priority\Api\Block\Adminhtml\Sales;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\AuthorizationInterface;

class RepostButton implements ButtonProviderInterface
{
    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var Context
     */
    private $context;

    /**
     * CustomButton constructor.
     *
     * @param AuthorizationInterface $authorization
     * @param Context $context
     */
    public function __construct(
        AuthorizationInterface $authorization,
        Context $context
    ) {
        $this->authorization = $authorization;
        $this->context = $context;
    }

    /**
     * @return array
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed('Magento_Sales::add')) {
            return [];
        }

        return [
            'label' => __('Repost'),
            'on_click' => sprintf("location.href = '%s';", $this->getBackUrl()),
            'class' => 'primary',
            'sort_order' => 20
        ];
    }

    /**
     * Get URL for back (reset) button
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->context->getUrlBuilder()->getUrl('priorityapi/order/repost/', []);
    }
}