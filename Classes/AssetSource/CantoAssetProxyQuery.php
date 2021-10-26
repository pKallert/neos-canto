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

use Flownative\Canto\Exception\AuthenticationFailedException;
use Flownative\OAuth2\Client\OAuthClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetCollection;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;
use Neos\Media\Domain\Model\Tag;
use Psr\Log\LoggerInterface as SystemLoggerInterface;

/**
 *
 */
final class CantoAssetProxyQuery implements AssetProxyQueryInterface
{
    /**
     * @var CantoAssetSource
     */
    private $assetSource;

    /**
     * @var string
     */
    private $searchTerm = '';

    /**
     * @var Tag
     */
    private $activeTag;

    /**
     * @var string
     */
    private $tagQuery = "";

    /**
     * @var AssetCollection
     */
    private $activeAssetCollection;

    /**
     * @var string
     */
    private $assetTypeFilter = 'All';

    /**
     * @var array
     */
    private $orderings = [];

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var int
     */
    private $limit = 30;

    /**
     * @Flow\InjectConfiguration(path="mapping", package="Flownative.Canto")
     * @var array
     */
    protected $mapping = [];

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param CantoAssetSource $assetSource
     */
    public function __construct(CantoAssetSource $assetSource)
    {
        $this->assetSource = $assetSource;
    }

    /**
     * @param int $offset
     */
    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param string $searchTerm
     */
    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }

    /**
     * @return string
     */
    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    /**
     * @param Tag $tag
     */
    public function setActiveTag(Tag $tag): void
    {
        $this->activeTag = $tag;
    }

    /**
     * @return Tag
     */
    public function getActiveTag(): Tag
    {
        return $this->activeTag;
    }

    /**
     * @param AssetCollection|null $assetCollection
     */
    public function setActiveAssetCollection(AssetCollection $assetCollection = null): void
    {
        $this->activeAssetCollection = $assetCollection;
    }

    /**
     * @return AssetCollection|null
     */
    public function getActiveAssetCollection(): ?AssetCollection
    {
        return $this->activeAssetCollection;
    }

    /**
     * @param string $assetTypeFilter
     */
    public function setAssetTypeFilter(string $assetTypeFilter): void
    {
        $this->assetTypeFilter = $assetTypeFilter;
    }

    /**
     * @return string
     */
    public function getAssetTypeFilter(): string
    {
        return $this->assetTypeFilter;
    }

    /**
     * @return array
     */
    public function getOrderings(): array
    {
        return $this->orderings;
    }

    /**
     * @param array $orderings
     */
    public function setOrderings(array $orderings): void
    {
        $this->orderings = $orderings;
    }

    /**
     * @return AssetProxyQueryResultInterface
     */
    public function execute(): AssetProxyQueryResultInterface
    {
        return new CantoAssetProxyQueryResult($this);
    }

    /**
     * @return int
     * @throws AuthenticationFailedException
     * @throws IdentityProviderException
     * @throws OAuthClientException
     * @throws GuzzleException
     */
    public function count(): int
    {
        $response = $this->sendSearchRequest(1, []);
        $responseObject = \GuzzleHttp\json_decode($response->getBody());
        return $responseObject->found ?? 0;
    }

    /**
     * @return CantoAssetProxy[]
     * @throws AuthenticationFailedException
     * @throws IdentityProviderException
     * @throws OAuthClientException
     * @throws GuzzleException
     * @throws \Neos\Cache\Exception
     * @throws \Neos\Cache\Exception\InvalidDataException
     */
    public function getArrayResult(): array
    {
        $assetProxies = [];
        $response = $this->sendSearchRequest($this->limit, $this->orderings);
        $responseObject = \GuzzleHttp\json_decode($response->getBody());

        if (isset($responseObject->results) && is_array($responseObject->results)) {
            foreach ($responseObject->results as $rawAsset) {
                $assetIdentifier = $rawAsset->scheme . '-' . $rawAsset->id;

                $this->assetSource->getAssetProxyCache()->set($assetIdentifier, \GuzzleHttp\json_encode($rawAsset, JSON_FORCE_OBJECT));

                $assetProxies[] = CantoAssetProxy::fromJsonObject($rawAsset, $this->assetSource);
            }
        }
        return $assetProxies;
    }

    /**
     * @param int $limit
     * @param array $orderings
     * @return Response
     * @throws OAuthClientException
     * @throws GuzzleException
     */
    private function sendSearchRequest(int $limit, array $orderings): Response
    {
        if (!empty($this->activeTag)) {
            $this->prepareTagQuery();
        }

        $searchTerm = $this->searchTerm;

        switch ($this->assetTypeFilter) {
            case 'Image':
                $formatTypes = ['image'];
            break;
            case 'Video':
                $formatTypes = ['video'];
            break;
            case 'Audio':
                $formatTypes = ['audio'];
            break;
            case 'Document':
                $formatTypes = ['document'];
            break;
            case 'All':
            default:
                $formatTypes = ['image', 'video', 'audio', 'document', 'presentation', 'other'];
            break;
        }
        return $this->assetSource->getCantoClient()->search($searchTerm, $formatTypes, $this->tagQuery, $this->offset, $limit, $orderings);
    }

    /**
     * @return void
     * @throws OAuthClientException
     * @throws GuzzleException
     */
    public function prepareTagQuery(): void
    {
        $assetCollectionTitlesToSearch = [];
        if (!empty($this->activeAssetCollection)) {
            $assetCollectionTitlesToSearch[] = $this->activeAssetCollection->getTitle();
        } else {
            foreach ($this->activeTag->getAssetCollections() as $collection) {
                $assetCollectionTitlesToSearch[] = $collection->getTitle();
            }
        }

        $this->tagQuery = '';

        if (!empty($this->mapping['customFields'])) {
            $cantoCustomFields = $this->assetSource->getCantoClient()->getCustomFields();

            foreach ($cantoCustomFields as $cantoCustomField) {
                // field should not be mapped if it does not exist in the settings or if asAssetCollection is set to false
                if (!array_key_exists($cantoCustomField->id, $this->mapping['customFields']) || !$this->mapping['customFields'][$cantoCustomField->id]['asAssetCollection']) {
                    continue;
                }
                if (in_array($cantoCustomField->name, $assetCollectionTitlesToSearch, true)) {
                    $this->tagQuery .= '&' . $cantoCustomField->id . '.keyword="' . $this->activeTag->getLabel() . '"';
                }
            }
        }
    }

    /**
     * @return void
     * @throws OAuthClientException
     * @throws GuzzleException
     */
    public function prepareUntaggedQuery(): void
    {
        $this->tagQuery = '';
        if (!empty($this->mapping['customFields'])) {
            if ($this->activeAssetCollection !== null) {
                $cantoCustomFields = $this->assetSource->getCantoClient()->getCustomFields();
                foreach ($cantoCustomFields as $cantoCustomField) {
                    if ($this->mapping['customFields'][$cantoCustomField->id]['asAssetCollection'] && $cantoCustomField->name === $this->activeAssetCollection->getTitle()) {
                        $this->tagQuery .= '&' . $cantoCustomField->id . '.keyword="__null__"';
                    }
                }
            } else {
                foreach ($this->mapping['customFields'] as $customFieldId => $customFieldToBeMapped) {
                    if (!$customFieldToBeMapped['asAssetCollection']) {
                        continue;
                    }
                    $this->tagQuery .= '&' . $customFieldId . '.keyword="__null__"';
                }
            }
        }
    }
}
