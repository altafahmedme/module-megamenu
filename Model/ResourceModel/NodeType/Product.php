<?php
/**
 * Ecomteck
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 * https://ecomteck.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Ecomteck
 * @package     Ecomteck_Megamenu
 * @copyright   Copyright (c) 2019 Ecomteck (https://ecomteck.com/)
 * @license     https://ecomteck.com/LICENSE.txt
 */

namespace Ecomteck\Megamenu\Model\ResourceModel\NodeType;

use Magento\Store\Model\Store;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\EntityManager\MetadataPool;

class Product extends AbstractNode
{
    /**
     * @var CollectionFactory
     */
    private $productCollection;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * Product constructor.
     * @param ResourceConnection $resource
     * @param CollectionFactory $productCollection
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        ResourceConnection $resource,
        CollectionFactory $productCollection,
        MetadataPool $metadataPool
    ) {
        $this->productCollection = $productCollection;
        $this->metadataPool = $metadataPool;
        parent::__construct($resource);
    }

    /**
     * @param int   $storeId
     * @param array $productIds
     * @return array
     */
    public function fetchData($storeId = Store::DEFAULT_STORE_ID, $productIds = [])
    {
        $connection = $this->getConnection('read');
        $table = $this->getTable('url_rewrite');
        $select = $connection
            ->select()
            ->from($table, ['entity_id', 'request_path'])
            ->where('entity_type = ?', 'product')
            ->where('redirect_type = ?', 0)
            ->where('store_id = ?', $storeId)
            ->where('entity_id IN (?)', $productIds)
            ->where('metadata IS NULL');

        return $connection->fetchPairs($select);
    }

    /**
     * @param int $websiteId
     * @param int $customerGroupId
     * @param array $productIds
     * @return array
     */
    public function fetchPriceData($websiteId, $customerGroupId, $productIds = [])
    {
        $connection = $this->getConnection('read');
        $table = $this->getTable('catalog_product_index_price');
        $select = $connection
            ->select()
            ->from($table, ['entity_id', 'final_price'])
            ->where('customer_group_id = ?', $customerGroupId)
            ->where('website_id = ?', $websiteId)
            ->where('entity_id IN (?)', $productIds);

        return $connection->fetchPairs($select);
    }

    /**
     * @param int $storeId
     * @param array $productIds
     * @return array
     */
    public function fetchImageData($storeId, $productIds = [])
    {
        $collection = $this->productCollection->create();
        $collection->addAttributeToSelect(['thumbnail'], 'left')
            ->addFieldToFilter('entity_id', ['in' => $productIds])
            ->addStoreFilter($storeId);

        $imageData = [];
        foreach ($collection->getData() as $data) {
            $imageData[$data['entity_id']] = $data['thumbnail'] ?? '';
        }

        return $imageData;
    }

    /**
     * @inheritDoc
     */
    public function fetchConfigData()
    {
        return [];
    }

    /**
     * @param int $storeId
     * @param array $productIds
     * @return array
     */
    public function fetchTitleData($storeId = Store::DEFAULT_STORE_ID, $productIds = [])
    {
        $collection = $this->productCollection->create();
        $collection->addAttributeToSelect(['name'])
            ->addFieldToFilter('entity_id', ['in' => $productIds])
            ->addStoreFilter($storeId);

        $titleData = [];
        foreach ($collection->getData() as $data) {
            $titleData[$data['entity_id']] = $data['name'] ?? '';
        }

        return $titleData;
    }
}
