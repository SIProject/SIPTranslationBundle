<?php
/*
 * (c) Suhinin Ilja <iljasuhinin@gmail.com>
 */
namespace SIP\TranslationBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('sip_translation', 'array');

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