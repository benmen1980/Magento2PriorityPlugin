<?php
namespace Priority\Api\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class Responsepopupdata extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $urlBuilder;

    public function __construct(ContextInterface $context, UiComponentFactory $uiComponentFactory, UrlInterface $urlBuilder, array $components=[], array $data=[])
    {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
	    
		
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as & $item) {
			
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			
			$messages = $objectManager->create('Priority\Api\Model\ResourceModel\Transactions\Collection')->addFieldToFilter('order_increment_id', $item['increment_id'])->setOrder('entity_id', 'DESC')->setPageSize(1);
				$data=$messages->getData();
				$jresponse=array_column($data,'json_response');
				
				$status=array_column($data,'status');
				$color="";		
				if(isset($status[0]) && $status[0]=='Failed')
				{
					$color="red";
				}
				else
				{
					$color="blue";
				}
				
				    $item[$fieldName . '_html'] = "<a style='color:".$color. "'>View</a>";
                    $item[$fieldName . '_title'] = __('JSON Response');
                    $item[$fieldName . '_content'] = $jresponse;	
              
            }
			
		}


        return $dataSource;
    }
}