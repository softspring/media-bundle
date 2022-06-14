<?php

namespace Softspring\MediaBundle\DependencyInjection\Compiler;

use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MediaTypeProvidersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $mediaTypesCollection = $container->getDefinition(MediaTypesCollection::class);

        $taggedServices = $container->findTaggedServiceIds('sfs_media.media_type_provider');
        $providers = [];
        foreach ($taggedServices as $id => $tags) {
            $providers[$id] = new Reference($id);
        }

        $mediaTypesCollection->setArgument(0, $providers);
    }
}
