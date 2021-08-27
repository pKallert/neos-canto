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

use Flownative\Canto\AssetSource\CantoAssetSource;
use Flownative\Canto\Domain\Model\AccountAuthorization;
use Flownative\Canto\Domain\Repository\AccountAuthorizationRepository;
use Flownative\OAuth2\Client\OAuthClient;
use Flownative\OAuth2\Client\OAuthClientException;
use GuzzleHttp\Psr7\Query;
use League\OAuth2\Client\Provider\GenericProvider;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context;
use Psr\Http\Message\UriInterface;

/**
 * Canto OAuth Client
 */
class CantoOAuthClient extends OAuthClient
{
    protected string $baseUri = 'https://oauth.canto.global/oauth/api/oauth2';

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var AccountAuthorizationRepository
     */
    protected $accountAuthorizationRepository;

    public function getServiceType(): string
    {
        return CantoAssetSource::ASSET_SOURCE_IDENTIFIER;
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
        return trim($this->getBaseUri(), '/') . '/authorize';
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

    public function finishAuthorization(string $stateIdentifier, string $code, string $scope): UriInterface
    {
        $stateFromCache = $this->stateCache->get($stateIdentifier);
        if (empty($stateFromCache)) {
            throw new OAuthClientException(sprintf('OAuth2 (%s): Finishing authorization failed because oAuth state %s could not be retrieved from the state cache.', $this->getServiceType(), $stateIdentifier), 1627046882);
        }

        $authorizationId = $stateFromCache['authorizationId'];

        $returnUri = parent::finishAuthorization($stateIdentifier, $code, $scope);

        $accountIdentifier = null;
        if ($this->securityContext->isInitialized()) {
            $account = $this->securityContext->getAccount();
            if ($account instanceof Account) {
                $accountIdentifier = $account->getAccountIdentifier();
            }
        }

        if ($accountIdentifier === null) {
            throw new OAuthClientException(sprintf('OAuth2 (%s): Finishing authorization failed because no account identifier could be retrieved.', $this->getServiceType()), 1627046931);
        }

        $accountAuthorization = $this->accountAuthorizationRepository->findOneByFlowAccountIdentifier($accountIdentifier);
        if ($accountAuthorization === null) {
            $accountAuthorization = new AccountAuthorization();
            $accountAuthorization->setFlowAccountIdentifier($accountIdentifier);
            $this->accountAuthorizationRepository->add($accountAuthorization);
        } else {
            $this->accountAuthorizationRepository->update($accountAuthorization);
        }
        $accountAuthorization->setAuthorizationId($authorizationId);
        $this->persistenceManager->allowObject($accountAuthorization);
        $this->persistenceManager->persistAll();

        $queryParameterName = CantoOAuthClient::generateAuthorizationIdQueryParameterName(CantoAssetSource::ASSET_SOURCE_IDENTIFIER);
        $queryParameters = [];
        parse_str($returnUri->getQuery(), $queryParameters);
        unset($queryParameters[$queryParameterName]);

        return $returnUri->withQuery(Query::build($queryParameters));
    }
}
