<?php

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

use Behat\Transliterator\Transliterator;
use Exception;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\AssetProxyInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\HasRemoteOriginalInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\ProvidesOriginalUriInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Media\Domain\Model\ImportedAsset;
use Neos\Media\Domain\Repository\ImportedAssetRepository;
use Neos\Utility\MediaTypes;
use Psr\Http\Message\UriInterface;
use stdClass;

/**
 *
 */
final class CantoAssetProxy implements AssetProxyInterface, HasRemoteOriginalInterface, ProvidesOriginalUriInterface
{
    /**
     * @var CantoAssetSource
     */
    private $assetSource;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $label;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var \DateTime
     */
    private $lastModified;

    /**
     * @var int
     */
    private $fileSize;

    /**
     * @var string
     */
    private $mediaType;

    /**
     * @var array
     */
    private $iptcProperties = [];

    /**
     * @var UriInterface
     */
    private $thumbnailUri;

    /**
     * @var UriInterface
     */
    private $previewUri;

    /**
     * @var UriInterface
     */
    private $originalUri;

    /**
     * @var int
     */
    private $widthInPixels;

    /**
     * @var int
     */
    private $heightInPixels;

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @Flow\Inject
     * @var ImportedAssetRepository
     */
    protected $importedAssetRepository;

    /**
     * @param stdClass $jsonObject
     * @param CantoAssetSource $assetSource
     * @return static
     * @throws Exception
     */
    public static function fromJsonObject(stdClass $jsonObject, CantoAssetSource $assetSource): CantoAssetProxy
    {
        $assetProxy = new static();
        $assetProxy->assetSource = $assetSource;
        $assetProxy->identifier = $jsonObject->scheme . '|' . $jsonObject->id;
        $assetProxy->label = $jsonObject->name;
        $assetProxy->filename = $jsonObject->name;
        $assetProxy->lastModified = \DateTimeImmutable::createFromFormat('U', $jsonObject->time);
        $assetProxy->fileSize = $jsonObject->size;
        $assetProxy->mediaType = MediaTypes::getMediaTypeFromFilename($jsonObject->name);
        $assetProxy->tags = $jsonObject->tag ?? [];

        $assetProxy->widthInPixels = $jsonObject->width ?? null;
        $assetProxy->heightInPixels = $jsonObject->height ?? null;

        $assetProxy->thumbnailUri = new Uri($jsonObject->url->directUrlPreview);
        $assetProxy->previewUri = new Uri($jsonObject->url->directUrlPreview);
        $assetProxy->originalUri = new Uri($jsonObject->url->directUrlOriginal);

        return $assetProxy;
    }

    /**
     * @return AssetSourceInterface
     */
    public function getAssetSource(): AssetSourceInterface
    {
        return $this->assetSource;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getLastModified(): \DateTimeInterface
    {
        return $this->lastModified;
    }

    /**
     * @return int
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * @return string
     */
    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    /**
     * @param string $propertyName
     * @return bool
     */
    public function hasIptcProperty(string $propertyName): bool
    {
        return isset($this->iptcProperties[$propertyName]);
    }

    /**
     * @param string $propertyName
     * @return string
     */
    public function getIptcProperty(string $propertyName): string
    {
        return $this->iptcProperties[$propertyName] ?? '';
    }

    /**
     * @return array
     */
    public function getIptcProperties(): array
    {
        return $this->iptcProperties;
    }

    /**
     * @return int|null
     */
    public function getWidthInPixels(): ?int
    {
        return $this->widthInPixels;
    }

    /**
     * @return int|null
     */
    public function getHeightInPixels(): ?int
    {
        return $this->heightInPixels;
    }

    /**
     * @return UriInterface
     */
    public function getThumbnailUri(): ?UriInterface
    {
        return $this->thumbnailUri;
    }

    /**
     * @return UriInterface
     */
    public function getPreviewUri(): ?UriInterface
    {
        return $this->previewUri;
    }

    /**
     * @return resource
     */
    public function getImportStream()
    {
        return fopen($this->assetSource->getCantoClient()->directUri($this->identifier), 'rb');
    }

    /**
     * @return UriInterface
     */
    public function getOriginalUri(): UriInterface
    {
        return $this->assetSource->getCantoClient()->directUri($this->identifier);
    }

    /**
     * @return string
     */
    public function getLocalAssetIdentifier(): ?string
    {
        $importedAsset = $this->importedAssetRepository->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier($this->assetSource->getIdentifier(), $this->identifier);
        return ($importedAsset instanceof ImportedAsset ? $importedAsset->getLocalAssetIdentifier() : null);
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return bool
     */
    public function isImported(): bool
    {
        return true;
    }
}
