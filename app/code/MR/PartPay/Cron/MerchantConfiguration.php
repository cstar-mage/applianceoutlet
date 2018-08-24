<?php

namespace MR\PartPay\Cron;

class MerchantConfiguration
{
    /**
     *
     * @var \Magento\Framework\App\ObjectManager
     */
    protected $_objectManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var $_paymentUtil \MR\PartPay\Helper\PaymentUtil
     */
    protected $_paymentUtil;

    /**
     * MerchantConfiguration Cron constructor.
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_storeManager = $storeManager;
        $this->_paymentUtil = $this->_objectManager->get("\MR\PartPay\Helper\PaymentUtil");
    }

    public function execute()
    {
        $stores = $this->_storeManager->getStores();
        foreach (array_keys($stores) as $storeId){
            $merchantConfigurationManager = $this->_objectManager->create("\MR\PartPay\Model\Configuration");
            $configurationModel = $merchantConfigurationManager->load($storeId, "store_id");
            $this->_paymentUtil->refreshMerchantConfiguration($configurationModel, $storeId);
        }
    }

}
