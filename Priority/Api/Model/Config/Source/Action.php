<?php
namespace Priority\Api\Model\Config\Source;

class Action implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'get', 'label' => __('Get from API')],
			['value' => 'post', 'label' => __('Post to API')],
			['value' => 'patch', 'label' => __('Patch API')],
			['value' => 'delete', 'label' => __('Delete from API')]
		];
	}
}