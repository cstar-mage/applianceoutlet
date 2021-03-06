<?php
namespace MR\PartPay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class PaymentUtil extends AbstractHelper
{

    /**
     *
     * @var \Magento\Framework\App\ObjectManager
     */
    private $_objectManager;

    /**
     * Asset service
     *
     * @var \Magento\Framework\View\Asset\Repository
     */
    private $_assetRepo;

    /**
     * @var \MR\PartPay\Helper\Communication
     */
    protected $_communicationHelper;

    /**
     * @var $_storeManager \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var $_configHelper \MR\PartPay\Helper\Configuration
     */
    protected $_configHelper;

    public function __construct(
        Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_logger = $this->_objectManager->get("\MR\PartPay\Logger\PartPayLogger");
        $this->_assetRepo = $this->_objectManager->get("\Magento\Framework\View\Asset\Repository");
        $this->_communicationHelper = $this->_objectManager->get("\MR\PartPay\Helper\Communication");
        $this->_configHelper = $this->_objectManager->get("\MR\PartPay\Helper\Configuration");
        $this->_storeManager = $storeManager;
        $this->_logger->info(__METHOD__);
    }

    public function buildRedirectUrl()
    {
        $this->_logger->info(__METHOD__);
        $urlManager = $this->_objectManager->get('\Magento\Framework\Url');
        $url = $urlManager->getUrl('partpay/order/redirect', ['_secure' => true]);
        
        $this->_logger->info(__METHOD__ . " url: {$url} ");
        return $url;
    }

    public function saveInvalidRefundResponse($payment, $responseText)
    {
        $this->_logger->info(__METHOD__ . " responseText:{$responseText}");
        $info = [
            "Error" => $responseText,
        ];
        $payment->setAdditionalInformation(date("Y-m-d H:i:s"), json_encode($info));
        $payment->save();
        return $info;
    }

    public function savePartPayRefundResponse($payment, $responseBody)
    {
        $this->_logger->info(__METHOD__ . " responseBody:{$responseBody}");
        $response = json_decode($responseBody, true);
        if (isset($response['id'])) {
            $payment->setTransactionId($response['id']);
        }
        $payment->setAdditionalInformation(date("Y-m-d H:i:s"), $responseBody);
        $payment->save();

        return $response;
    }

    public function loadOrderById($orderId)
    {
        $this->_logger->info(__METHOD__ . " orderId:{$orderId}");
        
        $orderManager = $this->_objectManager->get('Magento\Sales\Model\Order');
        $order = $orderManager->loadByAttribute("entity_id", $orderId);
        $orderIncrementId = $order->getIncrementId();
        $this->_logger->info(__METHOD__ . " orderIncrementId:{$orderIncrementId}");
        if (!isset($orderIncrementId)) {
            return null;
        }
        return $order;
    }

    public function loadCustomerInfo($order)
    {
        $customerId = $order->getCustomerId();
        $this->_logger->info(__METHOD__ . " customerId:{$customerId}");
        $customerInfo = $this->_objectManager->create("\Magento\Framework\DataObject");
        
        $customerInfo->setId($customerId);
        
        $customerInfo->setName($order->getCustomerName());
        $customerInfo->setEmail($order->getCustomerEmail());
        
        try {
            $address = $order->getBillingAddress();
            if ($address) {
                $customerInfo->setPhoneNumber($address->getTelephone());
                
                $streetFull = implode(" ", $address->getStreet()) . " " . $address->getCity() . ", " . $address->getRegion() . " " . $address->getPostcode() . " " . $address->getCountryId();
                
                $customerInfo->setAddress($streetFull);
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->_logger->critical($e->_toString());
        }
        
        return $customerInfo;
    }

    public function findPartPayOrderForRefund($orderIncrementId, $info)
    {
        $this->_logger->info(__METHOD__);
        $partpayId = "";
        if (isset($info["orderId"])) {
            $partpayId = $info["orderId"];
            $this->_logger->info(__METHOD__ . "order:{$orderIncrementId} PartPayID: {$partpayId}");
            return $partpayId;
        }
        $this->_logger->info(__METHOD__ . " PartPayID not found");
        return $partpayId;
    }

    public function getWidgetHtml($totalAmount)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $merchantConfigurationManager = $this->_objectManager->create("\MR\PartPay\Model\Configuration");
        $configurationModel = $merchantConfigurationManager->load($storeId, "store_id");
        if ($configurationModel && !$configurationModel->getId()) {
            $configurationModel = $this->refreshMerchantConfiguration($configurationModel, $storeId);
        }

        if ($configurationModel->getMin() && $configurationModel->getMax()) {
            $merchantName = $this->_configHelper->getMerchantName($storeId) ?: 'your-merchant-name';
            $merchantName = urlencode(str_replace(' ', '-', $merchantName));

            return '<script async src="https://widgets.partpay.co.nz/' . $merchantName . '/partpay-widget-0.1.1.js?type=calculator&min=' . $configurationModel->getMin() . '&max=' . $configurationModel->getMax() . '&amount=' . $totalAmount . '" type="application/javascript"></script>';
        }
        return false;
    }

    public function refreshMerchantConfiguration($merchantConfigurationModel, $storeId)
    {
        $apiData = $this->_communicationHelper->getMerchantConfiguration($storeId);

        $merchantConfigurationModel->addData(
            array(
                "store_id" => $storeId,
                "min" => $apiData['minimumAmount'] ? $apiData['minimumAmount'] : '',
                "max" => $apiData['maximumAmount'] ? $apiData['maximumAmount'] : '',
            ));

        $merchantConfigurationModel->save();
        return $merchantConfigurationModel;
    }

}
