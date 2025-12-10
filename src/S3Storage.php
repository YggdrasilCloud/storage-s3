<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3;

use App\File\Domain\Model\StoredObject;
use App\File\Domain\Port\FileStorageInterface;
use Aws\S3\S3Client;

/**
 * Amazon S3 storage adapter.
 *
 * DSN format: storage://s3?bucket=my-bucket&region=eu-west-1&endpoint=https://s3.amazonaws.com
 *
 * Required options:
 * - bucket: S3 bucket name
 * - region: AWS region (e.g., "eu-west-1", "us-east-1")
 *
 * Optional:
 * - endpoint: Custom S3 endpoint (for S3-compatible services like MinIO)
 * - key: AWS access key ID (defaults to AWS SDK default credential chain)
 * - secret: AWS secret access key (required if key is provided)
 * - prefix: Key prefix for all files (e.g., "photos/")
 * - url_expiration: Presigned URL expiration in seconds (default: 3600)
 */
final readonly class S3Storage implements FileStorageInterface
{
    private string $bucket;
    private string $region;
    private ?string $prefix;
    private int $urlExpiration;
    private S3Client $client;

    /**
     * @param array<string,string> $options Configuration options from DSN
     */
    public function __construct(array $options)
    {
        // Validate required options
        if (!isset($options['bucket'])) {
            throw new \InvalidArgumentException('Missing required option: bucket');
        }

        if (!isset($options['region'])) {
            throw new \InvalidArgumentException('Missing required option: region');
        }

        $this->bucket = $options['bucket'];
        $this->region = $options['region'];
        $this->prefix = $options['prefix'] ?? null;
        $this->urlExpiration = (int) ($options['url_expiration'] ?? 3600);

        // Configure S3 client
        $clientConfig = [
            'version' => 'latest',
            'region' => $this->region,
        ];

        // Custom endpoint (for MinIO, DigitalOcean Spaces, etc.)
        if (isset($options['endpoint'])) {
            $clientConfig['endpoint'] = $options['endpoint'];
            // Use path-style endpoint for S3-compatible services (MinIO, etc.)
            $clientConfig['use_path_style_endpoint'] = true;
        }

        // Explicit credentials (optional, falls back to AWS SDK default chain)
        // Support both 'key'/'secret' and 'access_key'/'secret_key' formats
        $accessKey = $options['key'] ?? $options['access_key'] ?? null;
        $secretKey = $options['secret'] ?? $options['secret_key'] ?? null;

        if ($accessKey !== null && $secretKey !== null) {
            $clientConfig['credentials'] = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];
        }

        $this->client = new S3Client($clientConfig);
    }

    /**
     * Save a file stream to S3.
     *
     * @param resource $stream      File stream to save
     * @param string   $key         Storage key (e.g., "files/abc123/photo.jpg")
     * @param string   $mimeType    MIME type (e.g., "image/jpeg")
     * @param int      $sizeInBytes File size in bytes
     *
     * @return StoredObject Metadata about the stored file
     *
     * @throws \InvalidArgumentException If stream is invalid
     * @throws \RuntimeException         If S3 upload fails
     */
    public function save($stream, string $key, string $mimeType, int $sizeInBytes): StoredObject
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a valid resource');
        }

        $fullKey = $this->buildKey($key);

        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $fullKey,
                'Body' => $stream,
                'ContentType' => $mimeType,
                'ContentLength' => $sizeInBytes,
            ]);

            return new StoredObject(
                key: $key,
                adapter: 's3',
                storedAt: new \DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to upload file to S3: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Read a file as a stream from S3.
     *
     * @param string $key Storage key identifying the file
     *
     * @return resource File stream
     *
     * @throws \RuntimeException If file does not exist or cannot be read
     */
    public function readStream(string $key)
    {
        $fullKey = $this->buildKey($key);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $fullKey,
            ]);

            $body = $result['Body'];

            if (!$body instanceof \Psr\Http\Message\StreamInterface) {
                throw new \RuntimeException('S3 response body is not a stream');
            }

            $stream = $body->detach();

            if (null === $stream) {
                throw new \RuntimeException('Failed to detach stream from S3 response');
            }

            return $stream;
        } catch (\Aws\S3\Exception\S3Exception $e) {
            if (404 === $e->getStatusCode()) {
                throw new \RuntimeException(sprintf('File not found in S3: %s', $key), previous: $e);
            }

            throw new \RuntimeException(sprintf('Failed to read file from S3: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Delete a file from S3.
     *
     * @param string $key Storage key identifying the file
     *
     * @throws \RuntimeException If deletion fails
     */
    public function delete(string $key): void
    {
        $fullKey = $this->buildKey($key);

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $fullKey,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Failed to delete file from S3: %s', $e->getMessage()), previous: $e);
        }
    }

    /**
     * Check if a file exists in S3.
     *
     * @param string $key Storage key identifying the file
     */
    public function exists(string $key): bool
    {
        $fullKey = $this->buildKey($key);

        return $this->client->doesObjectExist($this->bucket, $fullKey);
    }

    /**
     * Get presigned URL for a file in S3.
     *
     * Returns a presigned URL valid for the configured expiration time (default: 1 hour).
     *
     * @param string $key Storage key identifying the file
     *
     * @return string Presigned S3 URL
     */
    public function url(string $key): ?string
    {
        $fullKey = $this->buildKey($key);

        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $fullKey,
        ]);

        $request = $this->client->createPresignedRequest(
            $command,
            sprintf('+%d seconds', $this->urlExpiration)
        );

        return (string) $request->getUri();
    }

    /**
     * Build full S3 key with optional prefix.
     */
    private function buildKey(string $key): string
    {
        if (null === $this->prefix) {
            return $key;
        }

        return rtrim($this->prefix, '/').'/'.ltrim($key, '/');
    }
}
