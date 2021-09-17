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
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException;

class AuthorizationController extends ActionController
{
    /**
     * Finish OAuth2 authorization
     *
     * @throws OAuthClientException
     * @throws StopActionException
     * @throws UnsupportedRequestTypeException
     */
    public function finishAction(string $state, string $code, string $scope = ''): void
    {
        $client = new CantoOAuthClient(CantoAssetSource::ASSET_SOURCE_IDENTIFIER);
        $uri = $client->finishAuthorization($state, $code, $scope);

        $this->response->setStatusCode(302);
        $this->response->setContent('<html><head><meta http-equiv="refresh" content="1;url=' . $uri . '"/></head><body><a href="' . $uri . '">Click to continue…</a></body></html>');
        throw new StopActionException();
    }
}
