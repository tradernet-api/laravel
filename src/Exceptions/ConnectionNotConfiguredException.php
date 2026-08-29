<?php

declare(strict_types=1);

namespace Tradernet\Laravel\Exceptions;

use InvalidArgumentException;

final class ConnectionNotConfiguredException extends InvalidArgumentException
{
    public static function missing(string $name): self
    {
        return new self(sprintf('Tradernet connection [%s] is not configured.', $name));
    }

    public static function missingCredentials(string $name): self
    {
        return new self(sprintf(
            'Tradernet connection [%s] requires TRADERNET_API_KEY and TRADERNET_API_SECRET (or api_key / api_secret in config).',
            $name,
        ));
    }
}
