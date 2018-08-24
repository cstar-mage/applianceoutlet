<?php
namespace MR\PartPay\Model\ResourceModel\Configuration;

use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('MR\PartPay\Model\Configuration', 'MR\PartPay\Model\ResourceModel\Configuration');
    }
}
