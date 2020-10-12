<?php

/**
 * Created by PhpStorm.
 * User: mnitin
 * Date: 10-01-2020
 * Time: 16:12
 */
namespace Priority\Api\Plugin;

use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as SalesOrderGridCollection;

class SalesOrderApiColumn
{

    private $messageManager;
    private $collection;

    public function __construct(MessageManager $messageManager,
        SalesOrderGridCollection $collection,
		\Magento\Framework\App\ResourceConnection $resourceConnection
    ) {

        $this->messageManager = $messageManager;
        $this->collection = $collection;
		$this->resourceConnection = $resourceConnection;
    }

     public function aroundGetReport(
        \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory $subject,
        \Closure $proceed,
        $requestName    
    ) {        
		
        $result = $proceed($requestName);
		
        if ($requestName == 'sales_order_grid_data_source') {
            if ($result instanceof $this->collection
            ) { 
			   $connection  = $this->resourceConnection->getConnection();
                $select = $this->collection->getSelect();
				$results = $this->resourceConnection->getConnection()->fetchAll($select);
				
            }

        }
        return $this->collection;
    }

}