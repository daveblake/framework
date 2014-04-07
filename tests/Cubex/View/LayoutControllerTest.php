<?php
namespace View;

use Cubex\Cubex;
use Cubex\Http\Request;
use Cubex\Routing\Route;
use Cubex\View\Layout;
use Cubex\View\LayoutController;
use Cubex\View\Renderable;
use namespaced\CubexProject;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LayoutControllerTest extends \PHPUnit_Framework_TestCase
{
  public function testBasics()
  {
    $controller = $this->getMockForAbstractClass(
      '\Cubex\View\LayoutController'
    );
    /**
     * @var $controller LayoutController
     */
    $output = 'layout control tester';
    $layout = new Layout(new CubexProject(), 'Default');
    $controller->setLayout($layout);
    $this->assertEquals($layout, $controller->layout());
    $layout->insert('testing', new Renderable($output));
    $this->assertContains($output, (string)$controller);
  }

  /**
   * @param $route
   * @param $expect
   *
   * @dataProvider responseProvider
   */
  public function testResponses($route, $expect)
  {
    $controller = new \namespaced\TestLayoutController();
    $controller->setCubex(new Cubex());
    $response = $controller->executeRoute(
      Route::create($route),
      Request::createFromGlobals(),
      HttpKernelInterface::MASTER_REQUEST,
      false
    );

    $this->assertContains($expect, $response->getContent());
  }

  public function responseProvider()
  {
    return [
      ['test', 'test renderTest'],
      ['echo', 'test renderEcho'],
      ['view', 'View Model Test'],
      ['json', '{"test":"json"}'],
    ];
  }
}


