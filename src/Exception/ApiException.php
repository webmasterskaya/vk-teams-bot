<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ResponseInterface $response,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        $message ??= self::descriptionFromDecoded(json_decode((string) $response->getBody(), true))
            ?? "Bot API returned HTTP {$response->getStatusCode()}";
        parent::__construct($message, previous: $previous);
    }

    private static function descriptionFromDecoded(mixed $data): ?string
    {
        return is_array($data) && isset($data['description']) && is_scalar($data['description'])
            ? (string) $data['description']
            : null;
    }
}
