<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Tests;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];
    /** @var list<ResponseInterface> */
    public array $responses = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        return array_shift($this->responses) ?? new Response(200, [], '{"ok":true}');
    }
}
