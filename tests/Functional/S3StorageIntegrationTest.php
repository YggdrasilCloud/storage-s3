<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3\Tests\Functional;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use YggdrasilCloud\StorageS3\S3Storage;

/**
 * Functional tests for S3Storage using MinIO.
 *
 * These tests require MinIO to be running:
 * docker compose up -d minio
 */
final class S3StorageIntegrationTest extends TestCase
{
    private S3Storage $storage;
    private S3Client $client;
    private string $bucket;

    protected function setUp(): void
    {
        $endpoint = $_ENV['S3_ENDPOINT'] ?? 'http://minio:9000';
        $region = $_ENV['S3_REGION'] ?? 'us-east-1';
        $key = $_ENV['S3_KEY'] ?? 'minioadmin';
        $secret = $_ENV['S3_SECRET'] ?? 'minioadmin';
        $bucket = $_ENV['S3_BUCKET'] ?? 'test-bucket';

        \assert(\is_string($endpoint));
        \assert(\is_string($region));
        \assert(\is_string($key));
        \assert(\is_string($secret));
        \assert(\is_string($bucket));

        $this->bucket = $bucket;

        // Create S3 client for test setup
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        // Create bucket if it doesn't exist
        if (!$this->client->doesBucketExist($this->bucket)) {
            $this->client->createBucket(['Bucket' => $this->bucket]);
            $this->client->waitUntil('BucketExists', ['Bucket' => $this->bucket]);
        }

        // Create storage instance
        /** @var array<string, string> $storageConfig */
        $storageConfig = [
            'bucket' => $this->bucket,
            'region' => $region,
            'endpoint' => $endpoint,
            'key' => $key,
            'secret' => $secret,
        ];
        $this->storage = new S3Storage($storageConfig);
    }

    protected function tearDown(): void
    {
        // Clean up all objects in the bucket
        try {
            $result = $this->client->listObjects(['Bucket' => $this->bucket]);
            $contents = $result['Contents'] ?? null;

            if (\is_array($contents)) {
                foreach ($contents as $object) {
                    if (\is_array($object) && isset($object['Key']) && \is_string($object['Key'])) {
                        $this->client->deleteObject([
                            'Bucket' => $this->bucket,
                            'Key' => $object['Key'],
                        ]);
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore cleanup errors
        }
    }

    public function testSaveAndReadStream(): void
    {
        // Create test file content
        $content = 'Hello, S3 Storage!';
        $stream = $this->createStream($content);

        // Save file
        $storedObject = $this->storage->save(
            $stream,
            'test/hello.txt',
            'text/plain',
            \strlen($content)
        );

        // Verify stored object properties
        // @phpstan-ignore property.notFound (anonymous class from S3Storage::save)
        self::assertSame('test/hello.txt', $storedObject->key);
        // @phpstan-ignore property.notFound
        self::assertSame('s3', $storedObject->adapter);
        // @phpstan-ignore property.notFound
        self::assertInstanceOf(\DateTimeImmutable::class, $storedObject->storedAt);

        // Read file back
        $readStream = $this->storage->readStream('test/hello.txt');

        $readContent = stream_get_contents($readStream);
        self::assertSame($content, $readContent);

        fclose($readStream);
    }

    public function testExists(): void
    {
        // File doesn't exist yet
        self::assertFalse($this->storage->exists('test/nonexistent.txt'));

        // Create and save file
        $stream = $this->createStream('test content');
        $this->storage->save($stream, 'test/exists.txt', 'text/plain', 12);

        // Now it exists
        self::assertTrue($this->storage->exists('test/exists.txt'));
    }

    public function testDelete(): void
    {
        // Create and save file
        $stream = $this->createStream('delete me');
        $this->storage->save($stream, 'test/delete.txt', 'text/plain', 9);

        // Verify it exists
        self::assertTrue($this->storage->exists('test/delete.txt'));

        // Delete file
        $this->storage->delete('test/delete.txt');

        // Verify it's gone
        self::assertFalse($this->storage->exists('test/delete.txt'));
    }

    public function testUrl(): void
    {
        // Create and save file
        $content = 'URL test content';
        $stream = $this->createStream($content);
        $this->storage->save($stream, 'test/url.txt', 'text/plain', \strlen($content));

        // Get presigned URL
        $url = $this->storage->url('test/url.txt');

        // Verify URL is valid and not empty
        self::assertNotEmpty($url);
        self::assertStringStartsWith('http', $url);
        self::assertStringContainsString($this->bucket, $url);
        self::assertStringContainsString('test/url.txt', $url);

        // Verify URL contains AWS signature parameters
        self::assertStringContainsString('X-Amz-Signature', $url);
        self::assertStringContainsString('X-Amz-Expires', $url);
    }

    public function testSaveWithPrefix(): void
    {
        // Create storage with prefix
        $region = $_ENV['S3_REGION'] ?? 'us-east-1';
        $endpoint = $_ENV['S3_ENDPOINT'] ?? 'http://minio:9000';
        $key = $_ENV['S3_KEY'] ?? 'minioadmin';
        $secret = $_ENV['S3_SECRET'] ?? 'minioadmin';

        \assert(\is_string($region));
        \assert(\is_string($endpoint));
        \assert(\is_string($key));
        \assert(\is_string($secret));

        /** @var array<string, string> $storageConfig */
        $storageConfig = [
            'bucket' => $this->bucket,
            'region' => $region,
            'endpoint' => $endpoint,
            'key' => $key,
            'secret' => $secret,
            'prefix' => 'photos/',
        ];
        $storage = new S3Storage($storageConfig);

        // Save file with prefix
        $stream = $this->createStream('prefixed content');
        $storage->save($stream, 'test.txt', 'text/plain', 16);

        // Verify file exists with full prefixed key
        $result = $this->client->listObjects(['Bucket' => $this->bucket]);
        $contents = $result['Contents'] ?? [];

        if (\is_array($contents)) {
            $keys = array_column($contents, 'Key');
            self::assertContains('photos/test.txt', $keys);
        } else {
            self::fail('Expected Contents to be an array');
        }
    }

    public function testReadStreamThrowsExceptionForNonexistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found in S3');

        $this->storage->readStream('test/nonexistent.txt');
    }

    public function testSaveBinaryFile(): void
    {
        // Create binary content (PNG header)
        $binaryContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";
        $stream = $this->createStream($binaryContent);

        // Save binary file
        $this->storage->save(
            $stream,
            'test/image.png',
            'image/png',
            \strlen($binaryContent)
        );

        // Read back and verify
        $readStream = $this->storage->readStream('test/image.png');
        $readContent = stream_get_contents($readStream);

        self::assertSame($binaryContent, $readContent);

        fclose($readStream);
    }

    /**
     * @return resource
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://temp', 'r+');

        if (false === $stream) {
            throw new \RuntimeException('Failed to create stream');
        }

        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }
}
