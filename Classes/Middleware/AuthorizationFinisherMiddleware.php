<?php
declare(strict_types=1);

namespace Flownative\Canto\Middleware;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Karsten Dambekalns, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\Canto\Domain\Model\AccountAuthorization;
use Flownative\Canto\Domain\Repository\AccountAuthorizationRepository;
use Flownative\Canto\Service\CantoOAuthClient;
use GuzzleHttp\Psr7\Query;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthorizationFinisherMiddleware implements MiddlewareInterface
{
    /**
     * @Flow\Inject
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @Flow\Inject(lazy=false)
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var AccountAuthorizationRepository
     */
    protected $accountAuthorizationRepository;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParameterName = CantoOAuthClient::generateAuthorizationIdQueryParameterName(CantoOAuthClient::SERVICE_TYPE);
        $queryParameters = $request->getQueryParams();
        $authorizationId = $queryParameters[$queryParameterName] ?? null;

        $accountIdentifier = null;
        if ($this->securityContext->isInitialized() === false && $this->securityContext->canBeInitialized()) {
            $this->securityContext->initialize();
        }

        $account = $this->securityContext->getAccount();
        if ($account instanceof Account) {
            $accountIdentifier = $account->getAccountIdentifier();
        }

        $response = null;
        if ($authorizationId !== null && $accountIdentifier !== null) {
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

            unset($queryParameters[$queryParameterName]);
            $cleanedRequestUri = $request->getUri()->withQuery(Query::build($queryParameters));
            $response = $this->responseFactory->createResponse(301)
                ->withHeader('Location', (string)$cleanedRequestUri)
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
                ->withHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
        }

        return $response ?? $handler->handle($request);
    }
}
