<?php
declare(strict_types=1);
namespace Flownative\Canto\Command;

use Flownative\Canto\AssetSource\CantoAssetProxy;
use Flownative\Canto\AssetSource\CantoAssetProxyRepository;
use Flownative\Canto\AssetSource\CantoAssetSource;
use Flownative\Canto\Exception\AccessToAssetDeniedException;
use Flownative\Canto\Exception\AuthenticationFailedException;
use Flownative\Canto\Exception\MissingClientSecretException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\AssetCollectionRepository;
use Neos\Media\Domain\Repository\TagRepository;
use Neos\Media\Domain\Service\AssetSourceService;
use Neos\Media\Domain\Model\AssetCollection;
use Neos\Media\Domain\Model\Tag;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;

class CantoCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\Inject
     * @var AssetSourceService
     */
    protected $assetSourceService;

    /**
     * @Flow\InjectConfiguration(path="commandController.customFieldsToImportAsCollectionsAndTags", package="Flownative.Canto")
     * @var array
     */
    protected $customFieldsToImportAsCollectionsAndTags;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @Flow\Inject
     * @var AssetCollectionRepository
     */
    protected $assetCollectionRepository;

    /**
     * Tag used assets
     *
     * @param string $assetSource Name of the canto asset source
     * @param bool $quiet If set, only errors will be displayed.
     * @return void
     */
    public function tagUsedAssetsCommand(string $assetSource = CantoAssetSource::ASSET_SOURCE_IDENTIFIER, bool $quiet = false): void
    {
        $assetSourceIdentifier = $assetSource;
        $iterator = $this->assetRepository->findAllIterator();

        !$quiet && $this->outputLine('<b>Tagging used assets of asset source "%s" via Canto API:</b>', [$assetSourceIdentifier]);

        try {
            $cantoAssetSource = $this->assetSourceService->getAssetSources()[$assetSourceIdentifier];
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
        $cantoAssetSource->getAssetProxyCache()->flush();

        foreach ($this->assetRepository->iterate($iterator) as $asset) {
            if (!$asset instanceof Asset) {
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

    /**
     * Import Canto Custom Fields as Tags and Collections
     *
     * @param string $username Name of the user that is used to authenticate in Canto
     * @param string $assetSource Name of the canto asset source
     * @param bool $quiet If set, only errors will be displayed.
     * @return void
     */
    public function importCustomFieldsAsCollectionsAndTagsCommand($username, string $assetSourceIdentifier = CantoAssetSource::ASSET_SOURCE_IDENTIFIER, bool $quiet = true): void
    {
        !$quiet && $this->outputLine('<b>Importing custom Fields as Tags and Collections via Canto API:</b>');

        try {
            $cantoAssetSource = $this->assetSourceService->getAssetSources()[$assetSourceIdentifier];
            $cantoClient = $cantoAssetSource->getCantoClient();
        } catch (\Exception $e) {
            $this->outputLine('<error>Canto Client error: Client could not be created</error>');
            exit(1);
        } 

        if (empty($this->customFieldsToImportAsCollectionsAndTags)) {
            $this->outputLine('<error>Configuration error: No Tags set to be imported</error>');
            exit(1);
        }

        $user = $this->userService->getUser($username); 
        if (!$user instanceof User) {
            $this->outputLine('<error>The user "%s" does not exist.</error>', [$username]);
            $this->quit(1);
        }

        try{
            $cantoClient->setAuthorizationByUser($user); 
        } catch (\Exception $e){
            $this->outputLine('<error>The user has not been authorized to use Canto.</error>'); 
            $this->quit(1); 
        }

        $cantoCustomFields = $cantoClient->getAllCustomFields();

        foreach($cantoCustomFields as $cantoCustomField){
            if(in_array($cantoCustomField->name, $this->customFieldsToImportAsCollectionsAndTags) && is_array($cantoCustomField->values)) {
                $assetCollection = $this->assetCollectionRepository->findOneByTitle($cantoCustomField->name); 

                if(!($assetCollection instanceof AssetCollection)){
                    $assetCollection = new AssetCollection($cantoCustomField->name);
                    $this->assetCollectionRepository->add($assetCollection);
                    $this->outputLine('Added assetCollection: '.$cantoCustomField->name); 
                }
                foreach($cantoCustomField->values as $cantoCustomFieldValue){
                    if(is_callable($cantoCustomFieldValue)){
                        continue; 
                    }
                    $tag = $this->tagRepository->findOneByLabel($cantoCustomFieldValue); 

                    if ($tag === null) {
                        $tag = new Tag($cantoCustomFieldValue);
                        $this->tagRepository->add($tag);
                        $this->outputLine('Added Tag: '.$cantoCustomFieldValue); 
                    }

                    if($assetCollection !== null  && !$assetCollection->getTags()->contains($tag)){
                        $assetCollection->addTag($tag);
                        $this->assetCollectionRepository->update($assetCollection);
                        $this->outputLine('Added Tag to Asset Collection: '.$cantoCustomFieldValue); 
                    }
                    

                }   

            }
        }

       
    }

}
