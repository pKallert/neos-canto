<?php
declare(strict_types=1);

namespace Flownative\Canto\Exception;

/*
 * This file is part of the Flownative.Canto package.
 *
 * (c) Robert Lemke, Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\Media\Domain\Model\AssetSource\AssetNotFoundExceptionInterface;

class AccessToAssetDeniedException extends Exception implements AssetNotFoundExceptionInterface
{
    protected $statusCode = 403;
}
