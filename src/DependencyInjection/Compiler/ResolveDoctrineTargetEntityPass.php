<?php

namespace Softspring\MediaBundle\DependencyInjection\Compiler;

use Softspring\CoreBundle\DependencyInjection\Compiler\AbstractResolveDoctrineTargetEntityPass;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveDoctrineTargetEntityPass extends AbstractResolveDoctrineTargetEntityPass
{
    protected function getEntityManagerName(ContainerBuilder $container): string
    {
        return $container->getParameter('sfs_media.entity_manager_name');
    }

    public function process(ContainerBuilder $container)
    {
        $this->setTargetEntityFromParameter('sfs_media.media.class', MediaInterface::class, $container, true);
        $this->setTargetEntityFromParameter('sfs_media.version.class', MediaVersionInterface::class, $container, true);
    }
}
