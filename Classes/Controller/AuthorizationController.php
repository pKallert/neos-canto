<?php
declare(strict_types=1);

namespace Flownative\Canto\Controller;

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
use Flownative\Canto\Service\CantoOAuthClient;
use Flownative\OAuth2\Client\OAuthClientException;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Context;
use Neos\Media\Domain\Service\AssetSourceService;

class AuthorizationController extends ActionController
{
    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var AssetSourceService
     */
    protected $assetSourceService;

    public function neededAction(string $returnUri): void
    {
        $this->view->assign('startUri', $this->uriBuilder->uriFor('start'));
        $this->view->assign('returnUri', $returnUri);
    }

    public function startAction(): void
    {
        $appId = $this->assetSourceService->getAssetSources()[CantoAssetSource::ASSET_SOURCE_IDENTIFIER]->getAppId();
        $appSecret = $this->assetSourceService->getAssetSources()[CantoAssetSource::ASSET_SOURCE_IDENTIFIER]->getAppSecret();
        if ($this->securityContext->isInitialized()) {
            $account = $this->securityContext->getAccount();
            if ($account) {
                $returnToUri = new Uri($this->uriBuilder->reset()->uriFor('finish'));
                $oAuthClient = new CantoOAuthClient(CantoAssetSource::ASSET_SOURCE_IDENTIFIER);
                $authorizationId = $oAuthClient->generateAuthorizationIdForAuthorizationCodeGrant($appId);
                $loginUri = $oAuthClient->startAuthorizationWithId(
                    $authorizationId,
                    $appId,
                    $appSecret,
                    $returnToUri,
                    ''
                );
                $oAuthClient->setAuthorizationMetadata($authorizationId, json_encode(['accountIdentifier' => $account->getAccountIdentifier()], JSON_THROW_ON_ERROR));
                $this->redirectToUri($loginUri);
            } else {
                throw new \RuntimeException('Could not retrieve account', 1632238529);
            }
        } else {
            throw new \RuntimeException('Security context is not initialized', 1632238616);
        }
    }

    /**
     * Finish OAuth2 authorization
     *
     * @throws OAuthClientException
     */
    public function finishAction(string $state, string $code, string $scope = ''): void
    {
        $client = new CantoOAuthClient(CantoAssetSource::ASSET_SOURCE_IDENTIFIER);
        $client->finishAuthorization($state, $code, $scope);
    }
}
