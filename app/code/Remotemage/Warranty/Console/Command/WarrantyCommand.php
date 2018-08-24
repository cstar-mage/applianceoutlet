<?php

namespace Remotemage\Warranty\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WarrantyCommand extends Command
{
    protected $collectionFactory;
    protected $jsonHelper;
    protected $dataObjectFactory;
    protected $customOptionsModel;
    protected $customOption;
    protected $objectManager;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \Magento\Framework\App\State $state,
        \Magento\Catalog\Model\Product\Option $customOptionsModel,
        \Magento\Catalog\Api\Data\ProductCustomOptionInterface $customOption,
        \Magento\Framework\ObjectManagerInterface $objectManager
    )
    {
        $this->objectManager = $objectManager;
        $this->collectionFactory = $collectionFactory;
        $this->jsonHelper = $jsonHelper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->customOptionsModel = $customOptionsModel;
        $this->customOption = $customOption;
        $state->setAreaCode('frontend');

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('remotemage:warranty')->setDescription('Read products warranty from synaps');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productCollection = $this->getProductCollection();
        foreach ($productCollection as $product) {
            $output->writeln($product->getName());
            $warrantyResults = $this->getProductWarrantyFromSynaps($product);
            if ($warrantyResults->TotalRecords > 0) {
                $output->writeln('found warranties');
                $this->filterWarrantyOptions($warrantyResults, $product);
                //$output->writeln('Found warranty for product');
            }
        }
        $output->writeln('Hey!');
    }

    protected function getProductCollection()
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');

        return $collection;
    }

    protected function getProductWarrantyFromSynaps($product)
    {
        $apiUrl = 'https://applianceoutlet.synapsnow.com/api/sales/warranties/options/searches';

        $filterObject = $this->dataObjectFactory->create();
        $filterObject->Type = 'forproduct';
        $filterObject->Value = $product->getSku();
        $dataObject = $this->dataObjectFactory->create();
        $dataObject->Page = 1;
        $dataObject->PageSize = 30;
        $dataObject->SortDescending = false;
        $dataObject->Filters = array($filterObject);

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
        //var_dump($response);die();
        return $response;
    }

    protected function filterWarrantyOptions($warrantyResult, $product)
    {
        $productPrice = $product->getPrice();
        $warrantOptions = array();
        foreach ($warrantyResult->Results as $option) {
            if ($productPrice > $option->PriceRangeMin && $productPrice <= $option->PriceRangeMax) {
                array_push($warrantOptions, $option);
            }
        }

        if (!empty($warrantOptions)) {
            var_dump('create product custom options');
            $this->createProductCustomOptions($warrantOptions, $product);
        }
    }

    protected function createProductCustomOptions($warranties, $product)
    {
        $customOptions = $this->customOptionsModel->getProductOptionCollection($product);
        if ($customOptions->getSize()) {
            //return;
        }

        // add default option
        $customOption = $this->customOption;
        $values[] = [
            'record_id' => '0',
            'title' => 'No Thanks',
            'price' => 0,
            'price_type' => 'fixed',
            'sku' => '',
            'sort_order' => 0
        ];
        $i = 1;
        foreach ($warranties as $warranty) {
            $warrantyOption = [
                'record_id' => 0,
                'title' => $warranty->WarrantyPeriodMonths.' months',
                'price' => $warranty->SellPrice,
                'price_type' => 'fixed',
                'sku' => $warranty->IntegrationCode,
                'sort_order' => $i++
            ];

            array_push($values, $warrantyOption);
        }
        $options = [[
            'sort_order' => 1,
            'title' => 'Premium Care Extended Warranty',
            'price_type' => 'fixed',
            'type' => 'radio',
            'is_require' => 1,
            'values' => $values
        ]];

        $product->setHasOptions(1);
        $product->setCanSaveCustomOptions(true);



        $customOptions = [];

        /** @var \Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory $customOptionFactory */
        $customOptionFactory = 
$this->objectManager->create(\Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory::class);

        foreach ($options as $option) {
            /** @var \Magento\Catalog\Api\Data\ProductCustomOptionInterface $customOption */
            $customOption = $customOptionFactory->create(['data' => $option]);
            $customOption->setProductSku($product->getSku());

            $customOptions[] = $customOption;
        }

        $product->setOptions($customOptions);

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryFactory */
        $productRepository = $this->objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $productRepository->save($product);
        var_dump('the product with options was saved');
//
//
//        foreach ($options as $arrayOption) {
//
//            $option = $this->customOptionsModel;
//            $option->setProductId($product->getId())
//            ->setStoreId($product->getStoreId())
//            ->addData($arrayOption);
//
//            $option->save();
//            $product->addOption($option);
//        }
//        $this->customOptionsModel->clearInstance();
//        //$product->setOptions($options)->save();

    }

    protected function apiTest($product)
    {
        $apiUrl = 'https://applianceoutlet.synapsnow.com/api/Products/Brands';

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
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
        $response = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $token), true );

        var_dump($response);
        return $response;
    }
}
