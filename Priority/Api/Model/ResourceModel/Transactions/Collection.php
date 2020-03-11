<?php 
namespace Priority\Api\Model\ResourceModel\Transactions;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection {

	public function _construct(){
		$this->_init("Priority\Api\Model\Transactions","Priority\Api\Model\ResourceModel\Transactions");
	}
	
}
 ?>