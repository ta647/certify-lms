<?php

declare(strict_types=1);

namespace Tests\Support;

use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * `Google\Client` に Guzzle の MockHandler を注入し、Google Calendar API への実通信を一切発生させずに
 * `GoogleCalendarService` をテストするためのヘルパー。
 *
 * `Google\Client::setHttpClient()` で差し込んだGuzzleクライアントは、OAuth のトークン交換・リフレッシュ
 * (`google/auth`の`HttpHandlerFactory::build()`経由)と、Calendar API本体の呼び出し(`REST::execute()`経由)の
 * どちらにも使われるため、1つのMockHandlerキューで両方のレスポンスを順番に積める。
 */
trait GoogleCalendarTestHelpers
{
    /**
     * @param array<int, Response> $responses リクエスト発生順に消費されるレスポンス列
     */
    protected function makeMockedGoogleClient(array $responses): Client
    {
        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        $client = new Client;
        $client->setClientId('test-client-id');
        $client->setClientSecret('test-client-secret');
        $client->setHttpClient($guzzle);

        return $client;
    }

    protected function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * Google OAuth2トークンエンドポイントの成功レスポンス。
     */
    protected function tokenResponse(string $accessToken = 'new-access-token', ?string $refreshToken = 'new-refresh-token', int $expiresIn = 3600): Response
    {
        $body = [
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'token_type' => 'Bearer',
        ];
        if ($refreshToken !== null) {
            $body['refresh_token'] = $refreshToken;
        }

        return $this->jsonResponse(200, $body);
    }

    /**
     * Google OAuth2トークンエンドポイントのエラーレスポンス(リフレッシュ失敗等)。
     */
    protected function tokenErrorResponse(string $error = 'invalid_grant'): Response
    {
        return $this->jsonResponse(400, ['error' => $error, 'error_description' => 'Token has been expired or revoked.']);
    }
}
