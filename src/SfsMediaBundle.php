<?php

namespace Softspring\MediaBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Softspring\MediaBundle\DependencyInjection\Compiler\AliasDoctrineEntityManagerPass;
use Softspring\MediaBundle\DependencyInjection\Compiler\MediaTypeProvidersPass;
use Softspring\MediaBundle\DependencyInjection\Compiler\NameGeneratorsPass;
use Softspring\MediaBundle\DependencyInjection\Compiler\ResolveDoctrineTargetEntityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SfsMediaBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $basePath = realpath(__DIR__.'/../config/doctrine-mapping/');

        $this->addRegisterMappingsPass($container, ["$basePath/model" => 'Softspring\MediaBundle\Model']);
        $this->addRegisterMappingsPass($container, ["$basePath/entities" => 'Softspring\MediaBundle\Entity']);

        $container->addCompilerPass(new AliasDoctrineEntityManagerPass());
        $container->addCompilerPass(new NameGeneratorsPass());
        $container->addCompilerPass(new MediaTypeProvidersPass());
        $container->addCompilerPass(new ResolveDoctrineTargetEntityPass());
    }

    /**
     * @param string|bool $enablingParameter
     */
    private function addRegisterMappingsPass(ContainerBuilder $container, array $mappings, $enablingParameter = false): void
    {
        $container->addCompilerPass(DoctrineOrmMappingsPass::createXmlMappingDriver($mappings, ['sfs_media.entity_manager_name'], $enablingParameter));
    }
}
