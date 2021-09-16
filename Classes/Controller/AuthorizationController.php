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
use Flownative\Canto\Domain\Model\AccountAuthorization;
use Flownative\Canto\Domain\Repository\AccountAuthorizationRepository;
use Flownative\Canto\Service\CantoOAuthClient;
use Flownative\OAuth2\Client\OAuthClientException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context;
use Neos\Flow\Security\Exception as SecurityException;

class AuthorizationController extends ActionController
{
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

    /**
     * Connect OAuth authorization to Neos account
     *
     * @throws StopActionException
     * @throws UnsupportedRequestTypeException
     * @throws IllegalObjectTypeException
     * @throws SecurityException
     */
    public function connectAction(string $authorizationId, string $returnUri): void
    {
        if (!$this->securityContext->isInitialized() && $this->securityContext->canBeInitialized()) {
            $this->securityContext->initialize();
        }
        if ($this->securityContext->isInitialized()) {
            $account = $this->securityContext->getAccount();
            if ($account instanceof Account) {
                $accountIdentifier = $account->getAccountIdentifier();
            } else {
                $this->throwStatus(500, 'No account found in security context');
            }
        } else {
            $this->throwStatus(500, 'Security context not initialized');
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

        $this->redirectToUri($returnUri);
    }
}
