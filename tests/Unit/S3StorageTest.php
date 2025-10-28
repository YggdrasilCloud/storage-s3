<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use YggdrasilCloud\StorageS3\S3Storage;

final class S3StorageTest extends TestCase
{
    public function testConstructorRequiresBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required option: bucket');

        new S3Storage([
            'region' => 'eu-west-1',
        ]);
    }

    public function testConstructorRequiresRegion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required option: region');

        new S3Storage([
            'bucket' => 'test-bucket',
        ]);
    }

    public function testConstructorAcceptsMinimalConfiguration(): void
    {
        $storage = new S3Storage([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
        ]);

        self::assertInstanceOf(S3Storage::class, $storage);
    }

    public function testConstructorAcceptsFullConfiguration(): void
    {
        $storage = new S3Storage([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
            'endpoint' => 'http://localhost:9000',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'prefix' => 'photos/',
            'url_expiration' => '7200',
        ]);

        self::assertInstanceOf(S3Storage::class, $storage);
    }

    public function testSaveThrowsExceptionWithInvalidStream(): void
    {
        $storage = new S3Storage([
            'bucket' => 'test-bucket',
            'region' => 'eu-west-1',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a valid resource');

        $storage->save('not-a-stream', 'test.txt', 'text/plain', 100);
    }
}
