<?php

declare(strict_types=1);

namespace PublishPhp\StatamicStandardSite;

/**
 * Result of a sync or delete operation.
 */
class SyncResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $uri = null,
        public readonly ?string $error = null,
        public readonly ?string $action = null, // 'created', 'updated', 'deleted', 'noop'
    ) {}

    public static function success(string $uri, string $action): self
    {
        return new self(success: true, uri: $uri, action: $action);
    }

    public static function failure(string $error): self
    {
        return new self(success: false, error: $error);
    }
}
