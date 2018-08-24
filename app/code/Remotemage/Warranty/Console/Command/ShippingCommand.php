<?php

namespace Remotemage\Warranty\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShippingCommand extends Command
{
    protected $collectionFactory;
    protected $jsonHelper;
    protected $dataObjectFactory;
    protected $customOptionsModel;
    protected $customOption;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Framework\App\State $state,
        \Magento\Catalog\Model\Product\Option $customOptionsModel,
        \Magento\Catalog\Api\Data\ProductCustomOptionInterface $customOption
    )
    {

        $this->collectionFactory = $collectionFactory;
        $this->jsonHelper = $jsonHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->customOptionsModel = $customOptionsModel;
        $this->customOption = $customOption;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('remotemage:shipping')->setDescription('Read shipping options from synaps');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Hey! We will get the shipping options');
        $this->apiTest();
    }

    

    protected function apiTest()
    {
        $apiUrl = 'https://applianceoutlet.synapsnow.com/api/shippingcalculators/simplegroups/shippingoptions';

        $dataObject = $this->dataObjectFactory->create();


        $dataObject->ReceiverName = 'Szilard Szegedi';
        $dataObject->ReceiverAddressLine1 = 'Dunarii 20B';
        $dataObject->ReceiverAddressLine2 = 'ap 71';
        $dataObject->ReceiverSuburb = 'test';
        $dataObject->ReceiverCity = 'Test';
        $dataObject->ReceiverZip ='123556';
        $dataObject->ReceiverState = 'Test';
        $dataObject->ReceiverCountry = 'New Zeeland';

        $dataObject->SenderName = '';
        $dataObject->SenderAddressLine1 = '';
        $dataObject->SenderAddressLine2 = '';
        $dataObject->SenderSuburb = '';
        $dataObject->SenderCity = '';
        $dataObject->SenderZip ='';
        $dataObject->SenderState = 'Test';
        $dataObject->SenderCountry = 'New Zeeland';

        $product = $this->dataObjectFactory->create();
        $product->Product_Code = 'SM-T800';
        $product->Quantity = 1;

        $dataObject->Products = array($product);


        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataObject));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                "Content-Type: application/json",
                "synapstoken: d2b2bf17-156a-4406-a903-02a1e3f9bf5d"
            )
        );

        $token = curl_exec($ch);
        var_dump($token);

        var_dump('rtest');
        $response = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $token) );
        var_dump($response);
        return $response;
    }
}