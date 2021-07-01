<?php
declare(strict_types=1);

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
    public const SERVICE_TYPE = 'canto';

    protected string $baseUri = 'https://oauth.canto.global/oauth/api/oauth2';

    public function getServiceType(): string
    {
        return self::SERVICE_TYPE;
    }

    public function setBaseUri(string $baseUri): void
    {
        $this->baseUri = $baseUri;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getAccessTokenUri(): string
    {
        return trim($this->getBaseUri(), '/') . '/token';
    }

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
        throw new \RuntimeException('not implemented');
    }

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
