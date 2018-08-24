<?php
namespace Remotemage\Warranty\Observer\Sales;

use Magento\Framework\Event\ObserverInterface;

class OrderLoadAfter implements ObserverInterface

{

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $warrantyPrice = '';
        $warrantyId = '';

        $order = $observer->getOrder();
        $items = $order->getItems();

        foreach ($items as $item) {
            $options = $item->getProductOptions();
            if (!is_array($options)) {
                continue;
            }

            if (!array_key_exists('options', $options)) {
                continue;
            }


            $optionId = $options['options'][0]['option_id'];
            if ($optionId) {
                $valueId = $options['options'][0]['option_value'];
            }

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productModel = $objectManager->get('\Magento\Catalog\Model\Product');
            $productObject = $productModel->load($item->getProductId());

            foreach ($productObject->getOptions() as $option) {
                $values = $option->getValues();
                if ($option->getId() == $optionId) {
                    foreach ($values as $v) {
                        if ($v->getOptionTypeId() == $valueId) {
                            $warrantyId = $v->getSku();
                            $warrantyPrice = $v->getPrice();
                        }

                    }
                }
            }

            $extensionAttributes = $item->getExtensionAttributes();
            if ($extensionAttributes === null) {
                $extensionAttributes = $this->getOrderItemExtensionDependency();
            }
            $extensionAttributes->setWarrantyOptionId($warrantyId);
            $extensionAttributes->setWarrantyPrice($warrantyPrice);

            $item->setExtensionAttributes($extensionAttributes);
            
        }
    }

    private function getOrderItemExtensionDependency()
    {
        $orderExtension = \Magento\Framework\App\ObjectManager::getInstance()->get(
            '\Magento\Sales\Api\Data\OrderItemExtension'
        );

        return $orderExtension;

    }

}