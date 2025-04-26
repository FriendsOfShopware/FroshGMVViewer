<?php

declare(strict_types=1);

namespace Frosh\GMVViewer;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshGMVViewer extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
