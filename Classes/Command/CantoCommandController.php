<?php
namespace Flownative\Canto\Command;

use Flownative\Canto\AssetSource\CantoAssetProxy;
use Flownative\Canto\AssetSource\CantoAssetProxyRepository;
use Flownative\Canto\AssetSource\CantoAssetSource;
use Flownative\Canto\Exception\AccessToAssetDeniedException;
use Flownative\Canto\Exception\AuthenticationFailedException;
use Flownative\Canto\Exception\Exception;
use Flownative\Canto\Exception\MissingClientSecretException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\AssetSource\AssetSourceAwareInterface;
use Neos\Media\Domain\Repository\AssetRepository;

class CantoCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\InjectConfiguration(path="assetSources", package="Neos.Media")
     * @var array
     */
    protected $assetSourcesConfiguration;

    /**
     * Tag used assets
     *
     * @param string $assetSource Name of the canto asset source
     * @param bool $quiet If set, only errors will be displayed.
     * @return void
     */
    public function tagUsedAssetsCommand(string $assetSource = 'flownative-canto', bool $quiet = false): void
    {
        $assetSourceIdentifier = $assetSource;
        $iterator = $this->assetRepository->findAllIterator();

        !$quiet && $this->outputLine('<b>Tagging used assets of asset source "%s" via Canto API:</b>', [$assetSourceIdentifier]);

        try {
            $cantoAssetSource = new CantoAssetSource($assetSourceIdentifier, $this->assetSourcesConfiguration[$assetSourceIdentifier]['assetSourceOptions']);
            $cantoClient = $cantoAssetSource->getCantoClient();
        } catch (MissingClientSecretException $e) {
            $this->outputLine('<error>Authentication error: Missing client secret</error>');
            exit(1);
        } catch (AuthenticationFailedException $e) {
            $this->outputLine('<error>Authentication error: %s</error>', [$e->getMessage()]);
            exit(1);
        }

        if (!$cantoAssetSource->isAutoTaggingEnabled()) {
            $this->outputLine('<error>Auto-tagging is disabled</error>');
            exit(1);
        }

        $assetProxyRepository = $cantoAssetSource->getAssetProxyRepository();
        assert($assetProxyRepository instanceof CantoAssetProxyRepository);
        $assetProxyRepository->getAssetProxyCache()->flush();

        foreach ($this->assetRepository->iterate($iterator) as $asset) {
            if (!$asset instanceof Asset) {
                continue;
            }
            if (!$asset instanceof AssetSourceAwareInterface) {
                continue;
            }
            if ($asset->getAssetSourceIdentifier() !== $assetSourceIdentifier) {
                continue;
            }

            try {
                $assetProxy = $asset->getAssetProxy();
            } catch (AccessToAssetDeniedException $exception) {
                $this->outputLine('   error   %s', [$exception->getMessage()]);
                continue;
            }

            if (!$assetProxy instanceof CantoAssetProxy) {
                $this->outputLine('   error   Asset "%s" (%s) could not be accessed via Canto-API', [$asset->getLabel(), $asset->getIdentifier()]);
                continue;
            }

            $currentTags = $assetProxy->getTags();
            sort($currentTags);
            if ($asset->getUsageCount() > 0) {
                $newTags = array_unique(array_merge($currentTags, [$cantoAssetSource->getAutoTaggingInUseTag()]));
                sort($newTags);

                if ($currentTags !== $newTags) {
                    $cantoClient->updateFile($assetProxy->getIdentifier(), ['keywords' => implode(',', $newTags)]);
                    $this->outputLine('   tagged   %s %s (%s)', [$asset->getLabel(), $assetProxy->getIdentifier(), $asset->getUsageCount()]);
                } else {
                    $this->outputLine('  (tagged)  %s %s (%s)', [$asset->getLabel(), $assetProxy->getIdentifier(), $asset->getUsageCount()]);
                }
            } else {
                $newTags = array_flip($currentTags);
                unset($newTags[$cantoAssetSource->getAutoTaggingInUseTag()]);
                $newTags = array_flip($newTags);
                sort($newTags);

                if ($currentTags !== $newTags) {
                    $cantoClient->updateFile($assetProxy->getIdentifier(), ['keywords' => implode(',', $newTags)]);
                    $this->outputLine('   removed %s', [$asset->getLabel(), $asset->getUsageCount()]);
                } else {
                    $this->outputLine('  (removed) %s', [$asset->getLabel(), $asset->getUsageCount()]);
                }
            }
        }
    }
}
