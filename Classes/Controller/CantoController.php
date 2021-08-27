<?php
declare(strict_types=1);

namespace Flownative\Canto\Controller;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\Canto\AssetSource\CantoAssetSource;
use Flownative\Canto\Exception\AuthenticationFailedException;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\Module\AbstractModuleController;

class CantoController extends AbstractModuleController
{
    /**
     * @Flow\InjectConfiguration(path="assetSources", package="Neos.Media")
     * @var array
     */
    protected $assetSourcesConfiguration;

    /**
     * @return void
     * @throws
     */
    public function indexAction(): void
    {
        $apiBaseUri = $this->assetSourcesConfiguration[CantoAssetSource::ASSET_SOURCE_IDENTIFIER]['assetSourceOptions']['apiBaseUri'];
        $this->view->assign('apiBaseUri', $apiBaseUri);

        try {
            $assetSource = new CantoAssetSource(CantoAssetSource::ASSET_SOURCE_IDENTIFIER, $this->assetSourcesConfiguration[CantoAssetSource::ASSET_SOURCE_IDENTIFIER]['assetSourceOptions']);
            $client = $assetSource->getCantoClient();
            $this->view->assign('user', $client->user());

            $this->view->assign('tree', $client->tree());

            $this->view->assign('connectionSucceeded', true);
        } catch (AuthenticationFailedException $e) {
            $this->view->assign('authenticationError', $e->getMessage());
        }
    }
}
