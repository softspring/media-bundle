<?php

namespace Softspring\MediaBundle\DependencyInjection\Compiler;

use Softspring\MediaBundle\Media\NameGenerators;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @deprecated use tagged_iterator in service definition
 */
class NameGeneratorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $nameGenerators = $container->getDefinition(NameGenerators::class);

        $taggedServices = $container->findTaggedServiceIds('sfs_media.name_generator');
        $generatorsReferences = [];
        foreach ($taggedServices as $id => $tags) {
            $generatorsReferences[$id] = new Reference($id);
        }

        $nameGenerators->setArgument(0, $generatorsReferences);
    }
}
