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
use Flownative\Canto\Exception\ConnectionException;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neos\Flow\Annotations\Inject;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;
use Neos\Media\Domain\Model\Tag;
use Neos\Media\Domain\Model\AssetCollection;
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
    private $tag;
    
    /**
     * @var string
     */
    private $tagQuery = "";


    /**
     * @var AssetCollection
     */
    private $assetCollection; 

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
     * @var string
     */
    private $parentFolderIdentifier = '';

    /**
     * @Inject
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
    public function setTag(Tag $tag): void
    {
        $this->tag = $tag;
    }

    /**
     * @return Tag
     */
    public function getTag(): Tag
    {
        return $this->tag;
    }

    /**
     * @param AssetCollection $assetCollection
     */
    public function setAssetCollection(AssetCollection $assetCollection = null): void
    {
        $this->assetCollection = $assetCollection;
    }

    /**
     * @return AssetCollection
     */
    public function getAssetCollection(): AssetCollection
    {
        return $this->assetCollection;
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
     * @return string
     */
    public function getParentFolderIdentifier(): string
    {
        return $this->parentFolderIdentifier;
    }

    /**
     * @param string $parentFolderIdentifier
     */
    public function setParentFolderIdentifier(string $parentFolderIdentifier): void
    {
        $this->parentFolderIdentifier = $parentFolderIdentifier;
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
     */
    public function count(): int
    {
        $response = $this->sendSearchRequest(1, []);
        $responseObject = \GuzzleHttp\json_decode($response->getBody());
        return $responseObject->found ?? 0;
    }

    /**
     * @return CantoAssetProxy[]
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
     * @throws AuthenticationFailedException
     * @throws ConnectionException
     * @throws IdentityProviderException
     */
    private function sendSearchRequest(int $limit, array $orderings): Response
    {
        if (!empty($this->tag)){
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
     */
    public function prepareTagQuery(): void 
    {
        $categoryList = $this->assetSource->getCantoClient()->getAllCustomFields(); 

        $assetTitles = array(); 
        if(!empty($this->assetCollection)){
            $assetTitles[] = $this->assetCollection->getTitle();
        } else {
            foreach($this->tag->getAssetCollections() as $collection){
                $assetTitles[] = $collection->getTitle(); 
            }
        
        }
        $this->tagQuery = ""; 
        foreach($categoryList as $cat){
            foreach($assetTitles as $title){
                if($cat->name === $title){
                    $this->tagQuery .= '&'.$cat->id.'.keyword="'.$this->tag->getLabel().'"'; 
                }
            }
            
        }
    }

    /**
     * @return void
     */
    public function prepareUntaggedQuery(): void 
    {
        $categoryList = $this->assetSource->getCantoClient()->getAllCustomFields(); 
        $this->tagQuery = ""; 

        if(!empty($this->assetCollection)){
            foreach($categoryList as $cat){
                if($cat->name == $this->assetCollection->getTitle()){
                    $this->tagQuery .= '&'.$cat->id.'.keyword="__null__"'; 
                }
            }
        } else {
            foreach($categoryList as $cat){
                $this->tagQuery .= '&'.$cat->id.'.keyword="__null__"'; 
            }
        }
     
        
    }

}
