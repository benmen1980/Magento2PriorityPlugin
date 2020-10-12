<?php 
namespace Priority\Api\Model\ResourceModel;

class Transactions extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb {
	public function _construct(){
		$this->_init("test_unit_transactions","entity_id");
	}
}
 ?>