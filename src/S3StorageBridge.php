<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3;

/**
 * Bridge for Amazon S3 storage adapter.
 *
 * This bridge is auto-discovered by the YggdrasilCloud core application
 * via Symfony service tagging (tag: storage.bridge).
 *
 * Supports driver: "s3"
 *
 * DSN example: storage://s3?bucket=my-bucket&region=eu-west-1
 */
final readonly class S3StorageBridge
{
    /**
     * Check if this bridge supports the given storage driver.
     *
     * @param string $driver Storage driver name (e.g., "s3", "ftp", "local")
     *
     * @return bool True if driver is "s3"
     */
    public function supports(string $driver): bool
    {
        return 's3' === $driver;
    }

    /**
     * Create an S3Storage instance from configuration.
     *
     * @param object $config Parsed storage configuration (StorageConfig compatible)
     *
     * @return S3Storage Storage adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid
     * @throws \RuntimeException         If adapter cannot be created
     */
    public function create(object $config): S3Storage
    {
        if (!isset($config->options) || !\is_array($config->options)) {
            throw new \InvalidArgumentException('Invalid storage configuration: missing options array');
        }

        /** @var array<string, string> $options */
        $options = $config->options;

        return new S3Storage($options);
    }
}
