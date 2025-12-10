<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3;

use App\File\Domain\Port\FileStorageInterface;
use App\File\Infrastructure\Storage\Bridge\StorageBridgeInterface;
use App\File\Infrastructure\Storage\StorageConfig;

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
final readonly class S3StorageBridge implements StorageBridgeInterface
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
     * @param StorageConfig $config Parsed storage configuration
     *
     * @return FileStorageInterface Storage adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid
     * @throws \RuntimeException         If adapter cannot be created
     */
    public function create(StorageConfig $config): FileStorageInterface
    {
        return new S3Storage($config->options);
    }
}
