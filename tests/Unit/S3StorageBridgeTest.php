<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3\Tests\Unit;

use App\File\Infrastructure\Storage\StorageConfig;
use PHPUnit\Framework\TestCase;
use YggdrasilCloud\StorageS3\S3Storage;
use YggdrasilCloud\StorageS3\S3StorageBridge;

final class S3StorageBridgeTest extends TestCase
{
    private S3StorageBridge $bridge;

    protected function setUp(): void
    {
        $this->bridge = new S3StorageBridge();
    }

    public function testSupportsS3Driver(): void
    {
        self::assertTrue($this->bridge->supports('s3'));
    }

    public function testDoesNotSupportOtherDrivers(): void
    {
        self::assertFalse($this->bridge->supports('local'));
        self::assertFalse($this->bridge->supports('ftp'));
        self::assertFalse($this->bridge->supports('gcs'));
    }

    public function testCreateReturnsS3StorageInstance(): void
    {
        $config = new StorageConfig(
            driver: 's3',
            options: [
                'bucket' => 'test-bucket',
                'region' => 'eu-west-1',
            ]
        );

        $storage = $this->bridge->create($config);

        self::assertInstanceOf(S3Storage::class, $storage);
    }

    public function testCreateThrowsExceptionWithMissingBucket(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required option: bucket');

        $config = new StorageConfig(
            driver: 's3',
            options: [
                'region' => 'eu-west-1',
            ]
        );

        $this->bridge->create($config);
    }
}
