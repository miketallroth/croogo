<?php

namespace Croogo\Core\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\ORM\TableRegistry;
use Croogo\Core\Croogo;
use Croogo\Core\TestSuite\CroogoTestCase;

class CroogoTest extends CroogoTestCase
{

    public $fixtures = [
//		'plugin.croogo/settings.setting',
    ];

    public function testCrossPluginHooks()
    {
        Plugin::load(['Shops', 'Suppliers'], [
            'bootstrap' => true,
        ]);

        $Orders = TableRegistry::get('Shops.Orders');
        $this->assertTrue($Orders->monitored);
    }

/**
 * test Croogo::hookApiComponent
 */
    public function testHookApiComponent()
    {
        $hooks = Configure::read('Hook.controller_properties');
        Configure::write('Hook.controller_properties', []);

        Croogo::hookApiComponent('Example', 'Example.ExampleApi');
        Croogo::hookApiComponent('Example', [
            'Users.UserApi' => [
                'priority' => 2,
            ],
        ]);

        $expected = [
            'Example' => [
                '_apiComponents' => [
                    'Example.ExampleApi' => [
                        'priority' => 8,
                    ],
                    'Users.UserApi' => [
                        'priority' => 2,
                    ],
                ],
            ],
        ];
        $result = Configure::read('Hook.controller_properties');
        $this->assertEquals($expected, $result);

        Configure::write('Hook.controller_properties', $hooks);
    }
}