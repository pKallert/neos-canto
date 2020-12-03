<?php

namespace Flownative\Canto\Service;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\OAuth2\Client\OAuthClient;
use League\OAuth2\Client\Provider\GenericProvider;


/**
 * Canto OAuth Client
 */
class CantoOAuthClient extends OAuthClient
{
    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @return string
     */
    public function getServiceType(): string
    {
        return 'canto';
    }

    /**
     * For example: https://oauth.canto.global/oauth/api/oauth2
     *
     * @param string $baseUri
     */
    public function setBaseUri(string $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    /**
     * For example: https://oauth.canto.global/oauth/api/oauth2
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * @return string
     */
    public function getAccessTokenUri(): string
    {
        return trim($this->getBaseUri(), '/') . '/token';
    }

    /**
     * @return string
     */
    public function getAuthorizeTokenUri(): string
    {
        return trim($this->getBaseUri(), '/') . '/token/authorize';
    }

    public function getResourceOwnerUri(): string
    {
        return trim($this->getBaseUri(), '/') . '/token/resource';
    }

    public function getClientId(): string
    {
        // TODO: Implement getClientId() method.
    }

    /**
     * @param string $clientId
     * @param string $clientSecret
     * @return GenericProvider
     */
    protected function createOAuthProvider(string $clientId, string $clientSecret): GenericProvider
    {
        return new CantoOAuthProvider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $this->renderFinishAuthorizationUri(),
            'urlAuthorize' => $this->getAuthorizeTokenUri(),
            'urlAccessToken' => $this->getAccessTokenUri(),
            'urlResourceOwnerDetails' => $this->getResourceOwnerUri(),
        ], [
            'requestFactory' => $this->getRequestFactory()
        ]);
    }
}
