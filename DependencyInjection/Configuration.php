<?php
/*
 * (c) Suhinin Ilja <isuhinin@armd.ru>
 */
namespace Armd\TranslationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class Configuration implements ConfigurationInterface
{
    /**
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('armd_translation', 'array');

        $rootNode
            ->children()
                ->scalarNode('default_lang')->defaultValue('ru')->end()
                ->arrayNode('allow_langs')
                    ->prototype('scalar')->end()
                    ->defaultValue(array('en'))
                ->end()
            ->end();

        return $treeBuilder;
    }
}