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

use Flownative\Canto\Service\AssetUpdateService;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Canto webhook receiver middleware
 */
class WebhookMiddleware implements MiddlewareInterface
{
    /**
     * @Flow\InjectConfiguration(path="webhook.pathPrefix")
     * @var string
     */
    protected $webhookPathPrefix;

    /**
     * @Flow\InjectConfiguration(path="webhook.token")
     * @var string
     */
    protected $webhookToken;

    /**
     * @Flow\Inject
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @Flow\Inject
     * @var AssetUpdateService
     */
    protected $assetUpdateService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestedPath = $request->getUri()->getPath();
        if (!str_starts_with($requestedPath, $this->webhookPathPrefix)) {
            return $handler->handle($request);
        }

        try {
            $payload = json_decode($request->getBody()->getContents(), true, 2, JSON_THROW_ON_ERROR);
            if (!$this->validatePayload($payload)) {
                return $this->responseFactory->createResponse(400, 'Invalid payload submitted');
            }
        } catch (\JsonException) {
            return $this->responseFactory->createResponse(400, 'Invalid payload submitted, parse error');
        }

        if ($this->webhookToken && $this->webhookToken !== $payload['secure_token']) {
            return $this->responseFactory->createResponse(403, 'Invalid token given');
        }

        $event = substr($requestedPath, strlen($this->webhookPathPrefix));
        if ($this->assetUpdateService->handleEvent($event, $payload)) {
            return $this->responseFactory->createResponse(204);
        }

        return $this->responseFactory->createResponse(500, 'Error during webhook processing');
    }

    private function validatePayload($metadata): bool
    {
        return is_array($metadata)
            && array_key_exists('secure_token', $metadata)
            && array_key_exists('scheme', $metadata)
            && array_key_exists('id', $metadata);
    }
}
