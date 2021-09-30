<?php
declare(strict_types=1);

namespace Flownative\Canto\AssetSource;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Exception;
use Flownative\Canto\Exception\AssetNotFoundException;
use Flownative\Canto\Exception\AuthenticationFailedException;
use Neos\Media\Domain\Model\AssetSource\AssetNotFoundExceptionInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\AssetProxyInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceConnectionExceptionInterface;
use Neos\Media\Domain\Model\AssetSource\AssetTypeFilter;
use Neos\Media\Domain\Model\AssetSource\SupportsSortingInterface;
use Neos\Media\Domain\Model\AssetSource\SupportsTaggingInterface;
use Neos\Media\Domain\Model\Tag;

/**
 * CantoAssetProxyRepository
 */
class CantoAssetProxyRepository implements AssetProxyRepositoryInterface, SupportsSortingInterface, SupportsTaggingInterface
{
    /**
     * @var CantoAssetSource
     */
    private $assetSource;

    /**
     * @param CantoAssetSource $assetSource
     */
    public function __construct(CantoAssetSource $assetSource)
    {
        $this->assetSource = $assetSource;
    }

    /**
     * @var string
     */
    private $assetTypeFilter = 'All';

    /**
     * @var array
     */
    private $orderings = [];

    /**
     * @param string $identifier
     * @return AssetProxyInterface
     * @throws AssetNotFoundExceptionInterface
     * @throws AssetSourceConnectionExceptionInterface
     * @throws AuthenticationFailedException
     * @throws AssetNotFoundException
     * @throws Exception
     */
    public function getAssetProxy(string $identifier): AssetProxyInterface
    {
        $cacheEntry = $this->assetSource->getAssetProxyCache()->get($identifier);
        if ($cacheEntry) {
            $responseObject = \GuzzleHttp\json_decode($cacheEntry);
        } else {
            $response = $this->assetSource->getCantoClient()->getFile($identifier);
            $responseObject = \GuzzleHttp\json_decode($response->getBody());
        }

        if (!$responseObject instanceof \stdClass) {
            throw new AssetNotFoundException('Asset not found', 1526636260);
        }

        $this->assetSource->getAssetProxyCache()->set($identifier, \GuzzleHttp\json_encode($responseObject, JSON_FORCE_OBJECT));

        return CantoAssetProxy::fromJsonObject($responseObject, $this->assetSource);
    }

    /**
     * @param AssetTypeFilter $assetType
     */
    public function filterByType(AssetTypeFilter $assetType = null): void
    {
        $this->assetTypeFilter = (string)$assetType ?: 'All';
    }

    /**
     * @return AssetProxyQueryResultInterface
     */
    public function findAll(): AssetProxyQueryResultInterface
    {
        $query = new CantoAssetProxyQuery($this->assetSource);
        $query->setAssetTypeFilter($this->assetTypeFilter);
        $query->setOrderings($this->orderings);
        return new CantoAssetProxyQueryResult($query);
    }

    /**
     * @param string $searchTerm
     * @return AssetProxyQueryResultInterface
     */
    public function findBySearchTerm(string $searchTerm): AssetProxyQueryResultInterface
    {
        $query = new CantoAssetProxyQuery($this->assetSource);
        $query->setSearchTerm($searchTerm);
        $query->setAssetTypeFilter($this->assetTypeFilter);
        $query->setOrderings($this->orderings);
        return new CantoAssetProxyQueryResult($query);
    }

    /**
     * @param Tag $tag
     * @return AssetProxyQueryResultInterface
     */
    public function findByTag(Tag $tag): AssetProxyQueryResultInterface
    {
        $query = new CantoAssetProxyQuery($this->assetSource);
        $query->setSearchTerm($tag->getLabel());
        $query->setAssetTypeFilter($this->assetTypeFilter);
        $query->setOrderings($this->orderings);
        return new CantoAssetProxyQueryResult($query);
    }

    /**
     * @return AssetProxyQueryResultInterface
     */
    public function findUntagged(): AssetProxyQueryResultInterface
    {
        $query = new CantoAssetProxyQuery($this->assetSource);
        $query->setAssetTypeFilter($this->assetTypeFilter);
        $query->setOrderings($this->orderings);
        return new CantoAssetProxyQueryResult($query);
    }

    /**
     * @return int
     */
    public function countAll(): int
    {
        $query = new CantoAssetProxyQuery($this->assetSource);
        return $query->count();
    }

    /**
     * Sets the property names to order results by. Expected like this:
     * array(
     *  'filename' => SupportsSorting::ORDER_ASCENDING,
     *  'lastModified' => SupportsSorting::ORDER_DESCENDING
     * )
     *
     * @param array $orderings The property names to order by by default
     * @return void
     * @api
     */
    public function orderBy(array $orderings): void
    {
        $this->orderings = $orderings;
    }

    public function countUntagged():int 
    {
        return 0;
    }

     /**
     * Counts Assets with the given Tag assigned
     *
     * @param Tag $tag
     * @param AssetCollection $assetCollection
     * @return integer
     */
    public function countByTag(Tag $tag, AssetCollection $assetCollection = null): int
    {
        return 20; 
        try {
            return (int)$query->getSingleScalarResult();
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }
    
}
