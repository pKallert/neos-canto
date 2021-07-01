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

use Flownative\Canto\Exception\AuthenticationFailedException;
use Flownative\OAuth2\Client\Authorization;
use Flownative\OAuth2\Client\OAuthClientException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Neos\Media\Domain\Model\AssetSource\SupportsSortingInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Canto API client
 */
final class CantoClient
{
    /**
     * @var string
     */
    private $apiBaseUri;

    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $appSecret;

    /**
     * @var Authorization
     */
    private $authorization;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @param string $apiBaseUri
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct(string $apiBaseUri, string $appId, string $appSecret)
    {
        $this->apiBaseUri = $apiBaseUri;
        $this->appId = $appId;
        $this->appSecret = $appSecret;

        $this->httpClient = new Client(['allow_redirects' => true]);
    }

    private function authenticate(): void
    {
        $oAuthClient = new CantoOAuthClient('canto');

        $authorizationId = Authorization::generateAuthorizationIdForClientCredentialsGrant('canto', $this->appId, $this->appSecret, 'admin');
        $this->authorization = $oAuthClient->getAuthorization($authorizationId);

        if ($this->authorization === null) {
            $oAuthClient->requestAccessToken('canto', $this->appId, $this->appSecret, 'admin');
            $this->authorization = $oAuthClient->getAuthorization($authorizationId);
        }

        if ($this->authorization === null) {
            throw new AuthenticationFailedException('Authentication failed: ' . ($result->help ?? 'Unknown cause'), 1607086346);
        }
    }

    /**
     * @param string $assetProxyId
     * @return ResponseInterface
     * @throws OAuthClientException
     */
    public function getFile(string $assetProxyId): ResponseInterface
    {
        [$scheme, $id] = explode('|', $assetProxyId);
        return $this->sendAuthenticatedRequest(
            $scheme . '/' . $id,
            'GET',
            []
        );
    }

    /**
     * @param string $id
     * @param array $metadata
     * @return ResponseInterface
     */
    public function updateFile(string $id, array $metadata): ResponseInterface
    {
        // TODO: Implement updateFile() method.
        throw new \RuntimeException('not implemented');
    }

    /**
     * @param string $keyword
     * @param array $formatTypes
     * @param array $fileTypes
     * @param int $offset
     * @param int $limit
     * @param array $orderings
     * @return ResponseInterface
     * @throws OAuthClientException
     */
    public function search(string $keyword, array $formatTypes, array $fileTypes, int $offset = 0, int $limit = 50, array $orderings = []): ResponseInterface
    {
        $pathAndQuery = 'search?keyword=' . urlencode($keyword);

        $pathAndQuery .= '&limit=' . urlencode((string)$limit);
        $pathAndQuery .= '&start=' . urlencode((string)$offset);

        if ($formatTypes !== []) {
            $pathAndQuery .= '&scheme=' . urlencode(implode('|', $formatTypes));
        }

        if (isset($orderings['filename'])) {
            $pathAndQuery .= '&sortBy=name';
            $pathAndQuery .= '&sortDirection=' . (($orderings['filename'] === SupportsSortingInterface::ORDER_DESCENDING) ? 'descending' : 'ascending');
        }

        if (isset($orderings['lastModified'])) {
            $pathAndQuery .= '&sortBy=time';
            $pathAndQuery .= '&sortDirection=' . (($orderings['lastModified'] === SupportsSortingInterface::ORDER_DESCENDING) ? 'descending' : 'ascending');
        }

        return $this->sendAuthenticatedRequest(
            $pathAndQuery,
            'GET',
            []
        );
    }

    /**
     * @return array
     * @throws OAuthClientException
     */
    public function user(): array
    {
        $response = $this->sendAuthenticatedRequest('user');
        if ($response->getStatusCode() === 200) {
            return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    /**
     * @return array
     * @throws OAuthClientException
     */
    public function tree(): array
    {
        $response = $this->sendAuthenticatedRequest('tree');
        if ($response->getStatusCode() === 200) {
            return \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    /**
     * @param string $assetProxyId
     * @return Uri|null
     * @throws OAuthClientException
     */
    public function directUri(string $assetProxyId): ?Uri
    {
        [$scheme, $id] = explode('|', $assetProxyId);

        $accessToken = $this->authorization->getAccessToken();

        $apiBinaryBaseUri = str_replace('/api/', '/api_binary/', $this->apiBaseUri);

        $request = new Request(
            'GET',
            $apiBinaryBaseUri . '/' . $scheme . '/' . $id . '/directuri',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ]
        );

        $response = $this->httpClient->send($request);

        if ($response->getStatusCode() === 200) {
            return new Uri($response->getBody()->getContents());
        }
        return null;
    }

    /**
     * @return string
     */
    public function getAccessToken(): ?AccessToken
    {
        return $this->authorization->getAccessToken();
    }

    /**
     * Returns a prepared request to an OAuth 2.0 service provider using Bearer token authentication
     *
     * @param Authorization $authorization
     * @param string $uriPathAndQuery A relative URI of the web server, prepended by the base URI
     * @param string $method The HTTP method, for example "GET" or "POST"
     * @param array $bodyFields Associative array of body fields to send (optional)
     * @return RequestInterface
     * @throws OAuthClientException
     */
    private function getAuthenticatedRequest(Authorization $authorization, string $uriPathAndQuery, string $method = 'GET', array $bodyFields = []): RequestInterface
    {
        $accessToken = $authorization->getAccessToken();
        if ($accessToken === null) {
            throw new OAuthClientException(sprintf('Canto: Failed getting an authenticated request for client ID "%s" because the authorization contained no access token', $authorization->getClientId()), 1607087086);
        }

        return new Request(
            $method,
            $this->apiBaseUri . '/' . $uriPathAndQuery,
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $authorization->getAccessToken()
            ],
            ($bodyFields !== [] ? \GuzzleHttp\json_encode($bodyFields) : '')
        );
    }

    /**
     * Sends an HTTP request to an OAuth 2.0 service provider using Bearer token authentication
     *
     * @param string $uriPathAndQuery
     * @param string $method
     * @param array $bodyFields
     * @return Response
     * @throws OAuthClientException
     */
    public function sendAuthenticatedRequest(string $uriPathAndQuery, string $method = 'GET', array $bodyFields = []): Response
    {
        if ($this->authorization === null) {
            $this->authenticate();
        }

        return $this->httpClient->send($this->getAuthenticatedRequest($this->authorization, $uriPathAndQuery, $method, $bodyFields));
    }
}
