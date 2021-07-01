<?php
declare(strict_types=1);

namespace Flownative\Canto\Domain\Model;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations\Entity;
use Neos\Flow\Annotations\Identity;

/**
 * @Entity()
 */
class AccountAuthorization
{
    /**
     * @Identity()
     * @var string
     */
    protected $flowAccountIdentifier;

    /**
     * @var string
     */
    protected $authorizationId;

    /**
     * @return string
     */
    public function getFlowAccountIdentifier(): string
    {
        return $this->flowAccountIdentifier;
    }

    /**
     * @param string $flowAccountIdentifier
     */
    public function setFlowAccountIdentifier(string $flowAccountIdentifier): void
    {
        $this->flowAccountIdentifier = $flowAccountIdentifier;
    }

    /**
     * @return string
     */
    public function getAuthorizationId(): string
    {
        return $this->authorizationId;
    }

    /**
     * @param string $authorizationId
     */
    public function setAuthorizationId(string $authorizationId): void
    {
        $this->authorizationId = $authorizationId;
    }
}
