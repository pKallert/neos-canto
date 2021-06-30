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
use Flownative\Canto\Service\CantoClient;
use GuzzleHttp\Psr7\Uri;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetSource\AssetProxyRepositoryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Utility\MediaTypes;

class CantoAssetSource implements AssetSourceInterface
{
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
    private $oAuthBaseUri;

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
     * @var array
     */
    private $assetSourceOptions;

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
     * @param string $assetSourceIdentifier
     * @param array $assetSourceOptions
     */
    public function __construct(string $assetSourceIdentifier, array $assetSourceOptions)
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,62}[a-z]$/', $assetSourceIdentifier) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid asset source identifier "%s". The identifier must match /^[a-z][a-z0-9-]{0,62}[a-z]$/', $assetSourceIdentifier), 1607085585);
        }

        $this->assetSourceIdentifier = $assetSourceIdentifier;
        $this->assetSourceOptions = $assetSourceOptions;

        foreach ($assetSourceOptions as $optionName => $optionValue) {
            switch ($optionName) {
                case 'apiBaseUri':
                    $uri = new Uri($optionValue);
                    $this->apiBaseUri = $uri->__toString();
                break;
                case 'oAuthBaseUri':
                    $uri = new Uri($optionValue);
                    $this->oAuthBaseUri = $uri->__toString();
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
                case 'description':
                    $this->$optionName = (string)$optionValue;
                break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown asset source option "%s" specified for Canto asset source "%s". Please check your settings.', $optionName, $assetSourceIdentifier), 1525790910);
            }
        }
    }

    /**
     * @param string $assetSourceIdentifier
     * @param array $assetSourceOptions
     * @return AssetSourceInterface
     */
    public static function createFromConfiguration(string $assetSourceIdentifier, array $assetSourceOptions): AssetSourceInterface
    {
        return new static($assetSourceIdentifier, $assetSourceOptions);
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->assetSourceIdentifier;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return 'Canto';
    }

    /**
     * @return AssetProxyRepositoryInterface
     */
    public function getAssetProxyRepository(): AssetProxyRepositoryInterface
    {
        if ($this->assetProxyRepository === null) {
            $this->assetProxyRepository = new CantoAssetProxyRepository($this);
        }

        return $this->assetProxyRepository;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getAssetSourceOptions(): array
    {
        return $this->assetSourceOptions;
    }

    /**
     * @return bool
     */
    public function isAutoTaggingEnabled(): bool
    {
        return $this->autoTaggingEnable;
    }

    /**
     * @return string
     */
    public function getAutoTaggingInUseTag(): string
    {
        return $this->autoTaggingInUseTag;
    }

    /**
     * @return string
     */
    public function getIconUri(): string
    {
        return $this->resourceManager->getPublicPackageResourceUriByPath($this->iconPath);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return CantoClient
     * @throws AuthenticationFailedException
     * @throws IdentityProviderException
     */
    public function getCantoClient(): CantoClient
    {
        if ($this->cantoClient === null) {
            $this->cantoClient = new CantoClient(
                $this->apiBaseUri,
                $this->oAuthBaseUri,
                $this->appId,
                $this->appSecret
            );

            $this->cantoClient->authenticate();
        }
        return $this->cantoClient;
    }
}
