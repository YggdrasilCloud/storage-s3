# YggdrasilCloud Storage S3

Amazon S3 storage adapter bridge for [YggdrasilCloud](https://github.com/YggdrasilCloud/core).

This package provides S3 storage backend support for YggdrasilCloud applications, allowing you to store files in Amazon S3 or S3-compatible services (MinIO, DigitalOcean Spaces, etc.).

## Installation

```bash
composer require yggdrasilcloud/storage-s3
```

The bridge is automatically discovered by Symfony via service tagging.

## Configuration

### Environment Variables

Set the `STORAGE_DSN` environment variable in your `.env` file:

```env
# Amazon S3 (with explicit credentials)
STORAGE_DSN=storage://s3?bucket=my-bucket&region=eu-west-1&key=AKIAIOSFODNN7EXAMPLE&secret=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY

# Amazon S3 (using AWS SDK default credential chain - recommended)
STORAGE_DSN=storage://s3?bucket=my-bucket&region=eu-west-1

# MinIO (custom endpoint)
STORAGE_DSN=storage://s3?bucket=my-bucket&region=us-east-1&endpoint=http://localhost:9000&key=minioadmin&secret=minioadmin

# DigitalOcean Spaces
STORAGE_DSN=storage://s3?bucket=my-space&region=fra1&endpoint=https://fra1.digitaloceanspaces.com&key=YOUR_KEY&secret=YOUR_SECRET

# With optional prefix for all files
STORAGE_DSN=storage://s3?bucket=my-bucket&region=eu-west-1&prefix=photos/
```

### DSN Parameters

#### Required

- `bucket`: S3 bucket name
- `region`: AWS region (e.g., `eu-west-1`, `us-east-1`)

#### Optional

- `endpoint`: Custom S3 endpoint for S3-compatible services (MinIO, DigitalOcean Spaces, etc.)
- `key`: AWS access key ID (if not provided, uses AWS SDK default credential chain)
- `secret`: AWS secret access key (required if `key` is provided)
- `prefix`: Key prefix for all stored files (e.g., `photos/`)
- `url_expiration`: Presigned URL expiration time in seconds (default: `3600` = 1 hour)

### MinIO (Local Development & Self-Hosting)

MinIO is an open-source S3-compatible object storage server perfect for:
- **Local development**: Test S3 integration without AWS costs
- **Self-hosting**: Run your own S3-compatible storage on-premises
- **CI/CD**: Integration tests with real S3 API

**Development setup**:
```env
STORAGE_DSN=storage://s3?bucket=my-bucket&region=us-east-1&endpoint=http://localhost:9000&key=minioadmin&secret=minioadmin
```

**Production self-hosting**:
```env
STORAGE_DSN=storage://s3?bucket=photos&region=us-east-1&endpoint=https://minio.example.com&key=YOUR_KEY&secret=YOUR_SECRET
```

MinIO is included in `compose.yaml` for local development. See [Development & Testing](#development--testing) section.

### AWS Credentials

The adapter supports multiple authentication methods:

1. **Explicit credentials in DSN** (not recommended for production):
   ```env
   STORAGE_DSN=storage://s3?bucket=my-bucket&region=eu-west-1&key=XXX&secret=YYY
   ```

2. **AWS SDK default credential chain** (recommended):
   - Environment variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`)
   - AWS credentials file (`~/.aws/credentials`)
   - IAM role for EC2 instances
   - IAM role for ECS tasks

   ```env
   # .env
   STORAGE_DSN=storage://s3?bucket=my-bucket&region=eu-west-1

   # Credentials via environment variables
   AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
   AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
   ```

## Usage

Once configured, the S3 storage adapter is automatically used by YggdrasilCloud when the DSN driver is `s3`.

The core application will use the `FileStorageInterface` to interact with S3:

```php
// In your application code (core)
$storage->save($stream, 'photos/abc123/image.jpg', 'image/jpeg', 12345);
$storage->exists('photos/abc123/image.jpg'); // true
$url = $storage->url('photos/abc123/image.jpg'); // Presigned URL
$stream = $storage->readStream('photos/abc123/image.jpg');
$storage->delete('photos/abc123/image.jpg');
```

## S3 Bucket Configuration

### CORS Configuration

If you need to access files from a web browser, configure CORS on your S3 bucket:

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "HEAD"],
        "AllowedOrigins": ["https://your-app.com"],
        "ExposeHeaders": ["ETag"],
        "MaxAgeSeconds": 3000
    }
]
```

### Bucket Policy (Optional)

For public read access (not recommended for most use cases):

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PublicRead",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::my-bucket/*"
        }
    ]
}
```

## Requirements

- PHP 8.4+
- YggdrasilCloud Core
- AWS SDK for PHP 3.x

## Development & Testing

**IMPORTANT**: All commands must be run via Docker Compose to ensure PHP 8.4 compatibility.

### Setup

```bash
# Start MinIO (S3-compatible local storage)
docker compose up -d minio

# Install dependencies (runs in ephemeral container)
docker compose run --rm app composer install
```

### Local S3 Testing with MinIO

MinIO is included in the Docker setup for local development and testing.

**Access MinIO Console**: http://localhost:9001
- Username: `minioadmin`
- Password: `minioadmin`

**Test configuration** (`.env.test`):
```env
STORAGE_DSN=storage://s3?bucket=test-bucket&region=us-east-1&endpoint=http://minio:9000&key=minioadmin&secret=minioadmin
```

**Create test bucket** (via MinIO Console or mc CLI):
```bash
# Using mc CLI inside MinIO container
docker compose exec minio mc alias set local http://localhost:9000 minioadmin minioadmin
docker compose exec minio mc mb local/test-bucket
```

### Quick Commands

```bash
# Run all quality checks + tests
docker compose run --rm app composer all

# Run code quality checks only (cs-fixer + phpstan)
docker compose run --rm app composer qual

# Run CI checks (lint + cs-fixer + phpstan + tests)
docker compose run --rm app composer ci
```

### Individual Tools

```bash
# PHP Lint
docker compose run --rm app composer lint

# Code style (fix)
docker compose run --rm app composer cs:fix

# Code style (check)
docker compose run --rm app composer cs:check

# Static analysis (PHPStan level 9)
docker compose run --rm app composer stan

# Unit tests only
docker compose run --rm app composer test

# Functional tests (requires MinIO running)
docker compose up -d minio
docker compose run --rm app vendor/bin/phpunit --testsuite=Functional

# All tests (unit + functional)
docker compose run --rm app vendor/bin/phpunit

# Mutation testing (Infection)
docker compose run --rm app composer infection
```

### Without Docker (Not Recommended)

If you have PHP 8.4 installed locally, you can run commands directly:

```bash
composer install
composer all
```

### Git Hooks (GrumPHP)

GrumPHP automatically runs quality checks on every commit:
- PHP Lint
- PHP CS Fixer
- PHPStan
- PHPUnit tests
- Infection mutation testing

Hooks are automatically installed after `composer install`.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## Security

If you discover a security vulnerability, please email security@yggdrasilcloud.com instead of opening a public issue.
