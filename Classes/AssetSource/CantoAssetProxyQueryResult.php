<?php
declare(strict_types=1);

namespace Flownative\Canto\AssetSource;

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
use Flownative\OAuth2\Client\OAuthClientException;
use GuzzleHttp\Exception\GuzzleException;
use Neos\Cache\Exception as CacheException;
use Neos\Cache\Exception\InvalidDataException;
use Neos\Media\Domain\Model\AssetSource\AssetProxy\AssetProxyInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryInterface;
use Neos\Media\Domain\Model\AssetSource\AssetProxyQueryResultInterface;

/**
 *
 */
class CantoAssetProxyQueryResult implements AssetProxyQueryResultInterface
{
    /**
     * @var array
     */
    private $assetProxies;

    /**
     * @var int
     */
    private $numberOfAssetProxies;

    /**
     * @var \ArrayIterator
     */
    private $assetProxiesIterator;

    /**
     * @param CantoAssetProxyQuery $query
     */
    public function __construct(private CantoAssetProxyQuery $query)
    {
    }

    /**
     * @throws OAuthClientException
     * @throws GuzzleException
     * @throws CacheException
     * @throws InvalidDataException
     * @throws AuthenticationFailedException
     */
    private function initialize(): void
    {
        if ($this->assetProxies === null) {
            $this->assetProxies = $this->query->getArrayResult();
            $this->assetProxiesIterator = new \ArrayIterator($this->assetProxies);
        }
    }

    public function getQuery(): AssetProxyQueryInterface
    {
        return clone $this->query;
    }

    /**
     * @throws CacheException
     * @throws GuzzleException
     * @throws InvalidDataException
     * @throws OAuthClientException
     * @throws AuthenticationFailedException
     */
    public function getFirst(): ?AssetProxyInterface
    {
        $this->initialize();
        return reset($this->assetProxies);
    }

    /**
     * @return AssetProxyInterface[]
     * @throws CacheException
     * @throws GuzzleException
     * @throws InvalidDataException
     * @throws OAuthClientException
     * @throws AuthenticationFailedException
     */
    public function toArray(): array
    {
        $this->initialize();
        return $this->assetProxies;
    }

    public function current()
    {
        $this->initialize();
        return $this->assetProxiesIterator->current();
    }

    public function next()
    {
        $this->initialize();
        $this->assetProxiesIterator->next();
    }

    public function key()
    {
        $this->initialize();
        return $this->assetProxiesIterator->key();
    }

    public function valid(): bool
    {
        $this->initialize();
        return $this->assetProxiesIterator->valid();
    }

    public function rewind()
    {
        $this->initialize();
        $this->assetProxiesIterator->rewind();
    }

    public function offsetExists($offset): bool
    {
        $this->initialize();
        return $this->assetProxiesIterator->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        $this->initialize();
        return $this->assetProxiesIterator->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->initialize();
        $this->assetProxiesIterator->offsetSet($offset, $value);
    }

    public function offsetUnset($offset)
    {
    }

    /**
     * @throws OAuthClientException
     * @throws GuzzleException
     * @throws AuthenticationFailedException
     */
    public function count(): int
    {
        if ($this->numberOfAssetProxies === null) {
            if (is_array($this->assetProxies)) {
                $this->numberOfAssetProxies = count($this->assetProxies);
            } else {
                $this->numberOfAssetProxies = $this->query->count();
            }
        }

        return $this->numberOfAssetProxies;
    }
}
