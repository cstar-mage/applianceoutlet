<?php
namespace MR\PartPay\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Configuration extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('mr_partpay_configuration', 'id');
    }
}
