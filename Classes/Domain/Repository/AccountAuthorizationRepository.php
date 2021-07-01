<?php
declare(strict_types=1);

namespace Flownative\Canto\Domain\Repository;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\Canto\Domain\Model\AccountAuthorization;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Persistence\Repository;

/**
 * @Scope("singleton")
 */
class AccountAuthorizationRepository extends Repository
{
    /**
     * @param string $accountIdentifier
     * @return AccountAuthorization|null
     */
    public function findOneByFlowAccountIdentifier(string $accountIdentifier): ?AccountAuthorization
    {
        return $this->__call('findOneByFlowAccountIdentifier', [$accountIdentifier]);
    }
}
