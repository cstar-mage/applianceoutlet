<?php
namespace Remotemage\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Imprasio extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'imprasio';

    protected $_dataObject;

    protected $_productRepository;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_dataObject = $dataObjectFactory;
        $this->_productRepository = $productRepository;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['imprasio' => $this->getConfigData('name')];
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */

        $options = $this->getShippingMethods($request);

        if (!$options) {
            return $result;
        }
        
        $i = 0;
        foreach ($options as $option) {
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier('imprasio');
            $method->setCarrierTitle($option->Description);

            $method->setMethod($i++);
            $method->setMethodTitle($option->Id);

            /*you can fetch shipping price from different sources over some APIs, we used price from config.xml - xml node price*/
            $amount = $option->Price;

            $method->setPrice($amount);
            $method->setCost($amount);

            $result->append($method);
        }

        return $result;
    }


    protected function getShippingMethods($request)
    {
        $apiUrl = 'https://applianceoutlet.synapsnow.com/api/shippingcalculators/simplegroups/shippingoptions';

        $dataObject = $this->_dataObject->create();

        $dataObject->ReceiverName = '';
        $dataObject->ReceiverAddressLine1 = $request->getDestStreet();
        $dataObject->ReceiverAddressLine2 = '';
        $dataObject->ReceiverSuburb = '';
        $dataObject->ReceiverCity = $request->getDestCity();
        $dataObject->ReceiverZip = $request->getDestPostcode();
        $dataObject->ReceiverState = $request->getDestRegionCode();
        $dataObject->ReceiverCountry = $request->getDestCountryId();

        $dataObject->SenderName = '';
        $dataObject->SenderAddressLine1 = '';
        $dataObject->SenderAddressLine2 = '';
        $dataObject->SenderSuburb = '';
        $dataObject->SenderCity = $request->getOrigCity();
        $dataObject->SenderZip = $request->getOrigPostcode();
        $dataObject->SenderState = $request->getOrigRegionId();
        $dataObject->SenderCountry = $request->getOrigCountryId();


        $products = array();
        $items = $request->getAllItems();

        foreach ($items as $item) {
            $product = $this->_productRepository->getById($item->getProductId());
            $productObj = $this->_dataObject->create();
            $productObj->Product_Code = $product->getSku();
            $productObj->Quantity = $item->getQty();

            array_push($products, $productObj);
        }

        $dataObject->Products = $products;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataObject));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
                "synapstoken: 108f713d-aa8d-40d9-9baf-8c9f01587790"
            )
        );

        $token = curl_exec($ch);

        $response = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $token) );

        if ($response->UnableToCalculate) {
            return false;
        }
        return $response->Options;
    }
}
