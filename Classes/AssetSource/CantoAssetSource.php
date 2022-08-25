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

use Flownative\Canto\Service\CantoClient;
use GuzzleHttp\Psr7\Uri;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\DependencyInjection\DependencyProxy;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Utility\MediaTypes;

class CantoAssetSource implements AssetSourceInterface
{
    public const ASSET_SOURCE_IDENTIFIER = 'flownative-canto';

    /**
     * @var bool
     */
    private $autoTaggingEnabled = false;

    /**
     * @var string
     */
    private $autoTaggingInUseTag = 'used-by-neos';

    /**
     * @var string
     */
    private $assetSourceIdentifier;

    /**
     * @var CantoAssetProxyRepository
     */
    private $assetProxyRepository;

    /**
     * @var string
     */
    private $apiBaseUri;

    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $appSecret;

    /**
     * @var CantoClient
     */
    private $cantoClient;

    /**
     * @var string
     */
    private $iconPath;

    /**
     * @var string
     */
    private $description;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var StringFrontend
     */
    protected $assetProxyCache;

    /**
     * @param string $assetSourceIdentifier
     * @param array $assetSourceOptions
     */
    public function __construct(string $assetSourceIdentifier, private array $assetSourceOptions)
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,62}[a-z]$/', $assetSourceIdentifier) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid asset source identifier "%s". The identifier must match /^[a-z][a-z0-9-]{0,62}[a-z]$/', $assetSourceIdentifier), 1607085585);
        }

        $this->assetSourceIdentifier = $assetSourceIdentifier;

        foreach ($assetSourceOptions as $optionName => $optionValue) {
            switch ($optionName) {
                case 'apiBaseUri':
                    $uri = new Uri($optionValue);
                    $this->apiBaseUri = $uri->__toString();
                break;
                case 'appId':
                    if (!is_string($optionValue) || empty($optionValue)) {
                        throw new \InvalidArgumentException(sprintf('Invalid app id specified for Canto asset source %s', $assetSourceIdentifier), 1607085646);
                    }
                    $this->appId = $optionValue;
                break;
                case 'appSecret':
                    if (!is_string($optionValue) || empty($optionValue)) {
                        throw new \InvalidArgumentException(sprintf('Invalid app secret specified for Canto asset source %s', $assetSourceIdentifier), 1607085604);
                    }
                    $this->appSecret = $optionValue;
                break;
                case 'mediaTypes':
                    if (!is_array($optionValue)) {
                        throw new \InvalidArgumentException(sprintf('Invalid media types specified for Canto asset source %s', $assetSourceIdentifier), 1542809628);
                    }
                    foreach ($optionValue as $mediaType => $mediaTypeOptions) {
                        if (MediaTypes::getFilenameExtensionsFromMediaType($mediaType) === []) {
                            throw new \InvalidArgumentException(sprintf('Unknown media type "%s" specified for Canto asset source %s', $mediaType, $assetSourceIdentifier), 1542809775);
                        }
                    }
                break;
                case 'iconPath':
                    $this->iconPath = (string)$optionValue;
                break;
                case 'description':
                    $this->description = (string)$optionValue;
                break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown asset source option "%s" specified for Canto asset source "%s". Please check your settings.', $optionName, $assetSourceIdentifier), 1525790910);
            }
        }

        if ($this->appId === null || $this->appSecret === null) {
            throw new \InvalidArgumentException(sprintf('No app id or app secret specified for Canto asset source "%s".', $assetSourceIdentifier), 1632468673);
        }
    }

    public static function createFromConfiguration(string $assetSourceIdentifier, array $assetSourceOptions): AssetSourceInterface
    {
        return new static($assetSourceIdentifier, $assetSourceOptions);
    }

    public function getIdentifier(): string
    {
        return $this->assetSourceIdentifier;
    }

    public function getLabel(): string
    {
        return 'Canto';
    }

    /**
     * @return CantoAssetProxyRepository
     */
    public function getAssetProxyRepository(): AssetProxyRepositoryInterface
    {
        if ($this->assetProxyRepository === null) {
            $this->assetProxyRepository = new CantoAssetProxyRepository($this);
        }

        return $this->assetProxyRepository;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getAssetSourceOptions(): array
    {
        return $this->assetSourceOptions;
    }

    public function isAutoTaggingEnabled(): bool
    {
        return $this->autoTaggingEnabled;
    }

    public function getAutoTaggingInUseTag(): string
    {
        return $this->autoTaggingInUseTag;
    }

    public function getIconUri(): string
    {
        return $this->resourceManager->getPublicPackageResourceUriByPath($this->iconPath);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getApiBaseUri(): string
    {
        return $this->apiBaseUri;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function getAppSecret(): string
    {
        return $this->appSecret;
    }

    public function getCantoClient(): CantoClient
    {
        if ($this->cantoClient === null) {
            $this->cantoClient = new CantoClient(
                $this->apiBaseUri,
                $this->appId,
                $this->appSecret,
                $this->assetSourceIdentifier
            );
        }
        return $this->cantoClient;
    }

    public function getAssetProxyCache(): StringFrontend
    {
        if ($this->assetProxyCache instanceof DependencyProxy) {
            $this->assetProxyCache->_activateDependency();
        }
        return $this->assetProxyCache;
    }
}
