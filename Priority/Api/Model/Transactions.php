<?php 
namespace Priority\Api\Model;

class Transactions extends \Magento\Framework\Model\AbstractModel {
	public function _construct(){
		$this->_init("Priority\Api\Model\ResourceModel\Transactions");
	}
}
?>