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

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class CmsPage extends AbstractNode
{
    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param ResourceConnection $resource
     * @param MetadataPool $metadataPool
     * @param PageRepositoryInterface $pageRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        ResourceConnection $resource,
        MetadataPool $metadataPool,
        PageRepositoryInterface $pageRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->metadataPool = $metadataPool;
        $this->pageRepository = $pageRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($resource);
    }

    /**
     * @return array
     */
    public function fetchConfigData()
    {
        $connection = $this->getConnection('read');

        $select = $connection->select()->from(
            $this->getTable('cms_page'),
            ['title', 'identifier']
        );

        return $connection->fetchPairs($select);
    }

    /**
     * @param int $storeId
     * @param array $pageIds
     * @return array
     * @throws LocalizedException
     */
    public function fetchData($storeId = Store::DEFAULT_STORE_ID, $pageIds = [])
    {
        $connection = $this->getConnection('read');
        $table = $this->getTable('url_rewrite');

        $select = $connection
            ->select()
            ->from($table, ['entity_id', 'request_path'])
            ->where('entity_type = ?', 'cms-page')
            ->where('store_id = ?', $storeId)
            ->where('entity_id IN (?)', array_values($pageIds));

        $urlsBasedOnRewrites = $connection->fetchPairs($select);

        $additionalPageUrls = [];
        $pageIdsWithMissingUrl = array_diff_key($pageIds, array_flip($urlsBasedOnRewrites));

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('page_id', $pageIdsWithMissingUrl, 'in')
            ->addFilter('store_id', [$storeId, Store::DEFAULT_STORE_ID], 'in')
            ->create();

        $pages = $this->pageRepository->getList($searchCriteria);

        foreach ($pages->getItems() as $page) {
            $additionalPageUrls[$page->getId()] = $page->getIdentifier();
        }

        return $urlsBasedOnRewrites + $additionalPageUrls;
    }

    /**
     * @param int|string $storeId
     * @param array $pagesCodes
     * @return array
     * @throws \Exception
     */
    public function getPageIds($storeId, $pagesCodes = [])
    {
        $metadata = $this->metadataPool->getMetadata(PageInterface::class);
        $identifierField = $metadata->getIdentifierField();
        $linkField = $metadata->getLinkField();

        $connection = $this->getConnection('read');

        $pageTable = $this->getTable('cms_page');
        $storeTable = $this->getTable('cms_page_store');

        $select = $connection->select()->from(
            ['p' => $pageTable],
            [$identifierField, 'identifier']
        )->join(
            ['s' => $storeTable],
            'p.' . $linkField . ' = s.' . $linkField,
            []
        )->where(
            's.store_id IN (0, ?)',
            $storeId
        )->where(
            'p.identifier IN (?)',
            $pagesCodes
        )->where(
            'p.is_active = ?',
            1
        )->order(
            's.store_id ASC'
        );

        $codes = $connection->fetchAll($select);

        $pageIds = [];

        foreach ($codes as $row) {
            $pageIds[$row['identifier']] = $row[$identifierField];
        }

        return $pageIds;
    }
}
