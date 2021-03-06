<?php

namespace Croogo\Menus\Test\TestCase\View\Helper;

use App\Controller\Component\SessionComponent;
use Cake\Controller\Controller;
use Croogo\Core\TestSuite\TestCase;
use Menus\View\Helper\MenusHelper;

class MenusHelperTest extends TestCase
{

    public $fixtures = [
        'plugin.users.user',
        'plugin.users.role',
        'plugin.settings.setting',
    ];

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->ComponentRegistry = new ComponentRegistry();

        $request = $this->getMock('Request');
        $response = $this->getMock('Response');
        $this->View = new View(new TheMenuTestController($request, $response));
        $this->Menus = new MenusHelper($this->View);
        $this->_appEncoding = Configure::read('App.encoding');
        $this->_asset = Configure::read('Asset');
        $this->_debug = Configure::read('debug');
    }

    /**
     * tearDown
     */
    public function tearDown()
    {
        Configure::write('App.encoding', $this->_appEncoding);
        Configure::write('Asset', $this->_asset);
        Configure::write('debug', $this->_debug);
        ClassRegistry::flush();
        unset($this->Layout);
    }

    /**
     * Test [menu] shortcode
     */
    public function testMenuShortcode()
    {
        $content = '[menu:blogroll]';
        $this->View->viewVars['menusForLayout']['blogroll'] = [
            'Menu' => [
                'id' => 6,
                'title' => 'Blogroll',
                'alias' => 'blogroll',
            ],
            'threaded' => [],
        ];
        Croogo::dispatchEvent('Helper.Layout.beforeFilter', $this->View, ['content' => &$content]);
        $this->assertContains('menu-6', $content);
        $this->assertContains('class="menu"', $content);
    }
}

//phpcs:disable
class TheMenuTestController extends Controller
{

    public $name = 'TheTest';

    public $uses = null;
}
//phpcs:enable
