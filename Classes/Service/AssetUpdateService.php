<?php
declare(strict_types=1);

namespace Flownative\Canto\Service;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Karsten Dambekalns, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\Canto\AssetSource\CantoAssetSource;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\AssetSource\AssetSourceInterface;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\ImportedAssetRepository;
use Neos\Media\Domain\Service\AssetService;
use Neos\Media\Domain\Service\AssetSourceService;
use Psr\Log\LoggerInterface;

/**
 * Canto asset update service for webhook handling
 */
final class AssetUpdateService
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ImportedAssetRepository
     */
    protected $importedAssetRepository;

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\Inject
     * @var AssetService
     */
    protected $assetService;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var AssetSourceService
     */
    protected $assetSourceService;

    public function handleEvent(string $event, array $payload): bool
    {
        switch ($event) {
            case 'update':
                return $this->handleAssetMetadataUpdated($payload);
            case 'add':
                return $this->handleNewAssetVersionAdded($payload);
        }

        return false;
    }

    public function handleAssetMetadataUpdated(array $payload): bool
    {
        $identifier = $this->buildIdentifier($payload['scheme'], $payload['id']);

        $importedAsset = $this->importedAssetRepository->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier(CantoAssetSource::ASSET_SOURCE_IDENTIFIER, $identifier);
        if ($importedAsset === null) {
            $this->logger->debug(sprintf('Metadata update skipped on non-imported asset %s', $identifier), LogEnvironment::fromMethodName(__METHOD__));
            return true;
        }

        $this->flushProxyForAsset($identifier);

        $this->logger->debug(sprintf('Metadata cache flushed for asset %s', $identifier), LogEnvironment::fromMethodName(__METHOD__));

        // leads to "Modifications are not allowed as soon as the PersistentResource has been published or persisted."
        // $proxy = $this->getAssetSource()->getAssetProxyRepository()->getAssetProxy($identifier);
        // $localAssetIdentifier = $importedAsset->getLocalAssetIdentifier();
        // $localAsset = $this->assetRepository->findByIdentifier($localAssetIdentifier);
        // $localAsset->getResource()->setFilename($proxy->getFilename());

        $this->replaceAsset($identifier);

        return true;
    }

    public function handleNewAssetVersionAdded(array $payload): bool
    {
        $identifier = $this->buildIdentifier($payload['scheme'], $payload['id']);

        $importedAsset = $this->importedAssetRepository->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier(CantoAssetSource::ASSET_SOURCE_IDENTIFIER, $identifier);
        if ($importedAsset === null) {
            $this->logger->debug(sprintf('Version update skipped on non-imported asset %s', $identifier), LogEnvironment::fromMethodName(__METHOD__));
            return true;
        }

        $this->flushProxyForAsset($identifier);
        $this->replaceAsset($identifier);

        return true;
    }

    // TODO this "works" but used assets still have the same filename when used in frontend, so it seems incomplete
    private function replaceAsset(string $identifier): void
    {
        $importedAsset = $this->importedAssetRepository->findOneByAssetSourceIdentifierAndRemoteAssetIdentifier(CantoAssetSource::ASSET_SOURCE_IDENTIFIER, $identifier);
        $localAssetIdentifier = $importedAsset->getLocalAssetIdentifier();

        /** @var AssetInterface $localAsset */
        $localAsset = $this->assetRepository->findByIdentifier($localAssetIdentifier);
        // TODO do we need to delete the "old" resource? then we need to grab it here…
        // $previousResource = $localAsset->getResource();

        $proxy = $this->getAssetSource()->getAssetProxyRepository()->getAssetProxy($identifier);
        $assetResource = $this->resourceManager->importResource($proxy->getImportStream());
        $assetResource->setFilename($proxy->getFilename());
        $this->assetService->replaceAssetResource($localAsset, $assetResource);

        // TODO do we need to delete the "old" resource? … to delete it here!
        // $this->resourceManager->deleteResource($previousResource);

        $this->logger->debug(sprintf('Replaced resource on %s from %s', $localAssetIdentifier, $identifier), LogEnvironment::fromMethodName(__METHOD__));
    }

    private function buildIdentifier(string $scheme, string $identifier): string
    {
        return sprintf('%s-%s', $scheme, $identifier);
    }

    private function flushProxyForAsset(string $identifier): void
    {
        $assetProxyCache = $this->getAssetSource()->getAssetProxyCache();

        if ($assetProxyCache->has($identifier)) {
            $affectedEntriesCount = $assetProxyCache->remove($identifier);
            $this->logger->debug(sprintf('Flushed asset proxy cache entry for %s, %u affected', $identifier, $affectedEntriesCount), LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->logger->debug(sprintf('No asset proxy cache entry for %s found', $identifier), LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    /**
     * @return AssetSourceInterface|CantoAssetSource
     */
    private function getAssetSource(): AssetSourceInterface
    {
        return $this->assetSourceService->getAssetSources()[CantoAssetSource::ASSET_SOURCE_IDENTIFIER];
    }
}
