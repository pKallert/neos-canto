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

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use UnexpectedValueException;

/**
 * Canto OAuth Provider
 */
final class CantoOAuthProvider extends GenericProvider
{
    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param mixed $grant
     * @throws IdentityProviderException
     */
    public function getAccessToken($grant, array $options = []): AccessTokenInterface
    {
        $grant = $this->verifyGrant($grant);

        $params = [
            'app_id' => $this->clientId,
            'app_secret' => $this->clientSecret,
        ];

        $params = $grant->prepareRequestParameters($params, $options);
        $request = $this->getAccessTokenRequest($params);

        $response = $this->getParsedResponse($request);
        if (false === is_array($response)) {
            throw new UnexpectedValueException(
                'Invalid response received from Authorization Server. Expected JSON.'
            );
        }

        // The Canto OAuth server uses camelCase instead of snake_case
        $normalizedResponse = [
            'access_token' => $response['accessToken'] ?? '',
            'expires_in' => $response['expiresIn'] ?? '',
            'token_type' => $response['tokenType'] ?? '',
            'refresh_token' => $response['refreshToken'] ?? null
        ];

        $preparedTokenResponse = $this->prepareAccessTokenResponse($normalizedResponse);
        return $this->createAccessToken($preparedTokenResponse, $grant);
    }

    protected function getAuthorizationParameters(array $options): array
    {
        $parameters = parent::getAuthorizationParameters($options);
        $parameters['app_id'] = $parameters['client_id'];
        unset($parameters['client_id'], $parameters['approval_prompt']);

        return $parameters;
    }
}
