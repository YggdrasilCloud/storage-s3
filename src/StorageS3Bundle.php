<?php

declare(strict_types=1);

namespace YggdrasilCloud\StorageS3;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for S3 storage adapter.
 *
 * Auto-registers the S3StorageBridge service when installed via Composer.
 */
final class StorageS3Bundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../config'));
        $loader->load('services.yaml');
    }
}
