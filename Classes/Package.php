<?php
declare(strict_types=1);

namespace Flownative\Canto;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Karsten Dambekalns, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\ORM\EntityManagerInterface;
use Flownative\Canto\Domain\Repository\AccountAuthorizationRepository;
use Flownative\OAuth2\Client\Authorization;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;

class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(
            UserService::class,
            'userDeleted',
            function (User $user) use ($bootstrap) {
                $accountAuthorizationRepository = $bootstrap->getObjectManager()->get(AccountAuthorizationRepository::class);
                $entityManager = $bootstrap->getObjectManager()->get(EntityManagerInterface::class);

                foreach ($user->getAccounts() as $account) {
                    $accountAuthorization = $accountAuthorizationRepository->findOneByFlowAccountIdentifier($account->getAccountIdentifier());
                    if ($accountAuthorization !== null) {
                        $authorizationId = $accountAuthorization->getAuthorizationId();
                        $authorization = $entityManager->find(Authorization::class, ['authorizationId' => $authorizationId]);
                        if ($authorization instanceof Authorization) {
                            $entityManager->remove($authorization);
                        }

                        $accountAuthorizationRepository->remove($accountAuthorization);
                    }
                }
            },
            '',
            false
        );
    }
}
