<?php

declare(strict_types=1);

namespace Webmasterskaya\VkTeamsBot\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class TransportException extends RuntimeException implements ClientExceptionInterface {}
