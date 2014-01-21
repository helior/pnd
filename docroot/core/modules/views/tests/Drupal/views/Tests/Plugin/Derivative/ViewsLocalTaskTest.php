<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Plugin\Derivative\ViewsLocalTaskTest.
 */

namespace Drupal\views\Tests\Plugin\Derivative;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\Derivative\ViewsLocalTask;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the views local task derivative.
 *
 * @see \Drupal\views\Plugin\Derivative\ViewsLocalTask
 */
class ViewsLocalTaskTest extends UnitTestCase {

  /**
   * The mocked route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $routeProvider;

  /**
   * The mocked key value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $state;

  protected $baseDefinition = array(
    'class' => '\Drupal\views\Plugin\Menu\LocalTask\ViewsLocalTask',
    'derivative' => '\Drupal\views\Plugin\Derivative\ViewsLocalTask'
  );

  /**
   * The tested local task derivative class.
   *
   * @var \Drupal\views\Plugin\Derivative\ViewsLocalTask
   */
  protected $localTaskDerivative;

  public static function getInfo() {
    return array(
      'name' => 'Views local task derivative',
      'description' => 'Tests the views local task derivative.',
      'group' => 'Views plugin',
    );
  }

  protected function setUp() {
    $this->routeProvider = $this->getMock('Drupal\Core\Routing\RouteProviderInterface');
    $this->state = $this->getMock('Drupal\Core\KeyValueStore\StateInterface');

    $this->localTaskDerivative = new TestViewsLocalTask($this->routeProvider, $this->state);
  }

  /**
   * Tests fetching the derivatives on no view with hook menu.
   *
   * @see \Drupal\views\Plugin\Derivative\ViewsLocalTask::getDerivativeDefinitions()
   */
  public function testGetDerivativeDefinitionsWithoutHookMenuViews() {
    $result = array();
    $this->localTaskDerivative->setApplicableMenuViews($result);

    $definitions = $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);
    $this->assertEquals(array(), $definitions);
  }

  /**
   * Tests fetching the derivatives on a view with without a local task.
   */
  public function testGetDerivativeDefinitionsWithoutLocalTask() {
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $display_plugin = $this->getMockBuilder('Drupal\views\Plugin\views\display\PathPluginBase')
      ->setMethods(array('getOption'))
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $display_plugin->expects($this->once())
      ->method('getOption')
      ->with('menu')
      ->will($this->returnValue(array('type' => 'normal')));
    $executable->display_handler = $display_plugin;

    $result = array(array($executable, 'page_1'));
    $this->localTaskDerivative->setApplicableMenuViews($result);

    $definitions = $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);
    $this->assertEquals(array(), $definitions);
  }

  /**
   * Tests fetching the derivatives on a view with a default local task.
   */
  public function testGetDerivativeDefinitionsWithLocalTask() {
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->any())
      ->method('id')
      ->will($this->returnValue('example_view'));
    $executable->storage = $storage;

    $display_plugin = $this->getMockBuilder('Drupal\views\Plugin\views\display\PathPluginBase')
      ->setMethods(array('getOption'))
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $display_plugin->expects($this->once())
      ->method('getOption')
      ->with('menu')
      ->will($this->returnValue(array('type' => 'tab', 'weight' => 12, 'title' => 'Example title')));
    $executable->display_handler = $display_plugin;

    $result = array(array($executable, 'page_1'));
    $this->localTaskDerivative->setApplicableMenuViews($result);

    // Mock the view route names state.
    $view_route_names = array();
    $view_route_names['example_view.page_1'] = 'view.example_view.page_1';
    $this->state->expects($this->once())
      ->method('get')
      ->with('views.view_route_names')
      ->will($this->returnValue($view_route_names));

    $definitions = $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);
    $this->assertCount(1, $definitions);
    $this->assertEquals('view.example_view.page_1', $definitions['view.example_view.page_1']['route_name']);
    $this->assertEquals(12, $definitions['view.example_view.page_1']['weight']);
    $this->assertEquals('Example title', $definitions['view.example_view.page_1']['title']);
    $this->assertEquals($this->baseDefinition['class'], $definitions['view.example_view.page_1']['class']);
    $this->assertTrue(empty($definitions['view.example_view.page_1']['tab_root_id']));
  }

  /**
   * Tests fetching the derivatives on a view which overrides an existing route.
   */
  public function testGetDerivativeDefinitionsWithOverrideRoute() {
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->any())
      ->method('id')
      ->will($this->returnValue('example_view'));
    $executable->storage = $storage;

    $display_plugin = $this->getMockBuilder('Drupal\views\Plugin\views\display\PathPluginBase')
      ->setMethods(array('getOption'))
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $display_plugin->expects($this->once())
      ->method('getOption')
      ->with('menu')
      ->will($this->returnValue(array('type' => 'tab', 'weight' => 12)));
    $executable->display_handler = $display_plugin;

    $result = array(array($executable, 'page_1'));
    $this->localTaskDerivative->setApplicableMenuViews($result);

    // Mock the view route names state.
    $view_route_names = array();
    // Setup a view which overrides an existing route.
    $view_route_names['example_view.page_1'] = 'example_overridden_route';
    $this->state->expects($this->once())
      ->method('get')
      ->with('views.view_route_names')
      ->will($this->returnValue($view_route_names));

    $definitions = $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);
    $this->assertCount(0, $definitions);
  }

  /**
   * Tests fetching the derivatives on a view with a default local task.
   */
  public function testGetDerivativeDefinitionsWithDefaultLocalTask() {
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->any())
      ->method('id')
      ->will($this->returnValue('example_view'));
    $executable->storage = $storage;

    $display_plugin = $this->getMockBuilder('Drupal\views\Plugin\views\display\PathPluginBase')
      ->setMethods(array('getOption'))
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $display_plugin->expects($this->exactly(2))
      ->method('getOption')
      ->with('menu')
      ->will($this->returnValue(array('type' => 'default tab', 'weight' => 12, 'title' => 'Example title')));
    $executable->display_handler = $display_plugin;

    $result = array(array($executable, 'page_1'));
    $this->localTaskDerivative->setApplicableMenuViews($result);

    // Mock the view route names state.
    $view_route_names = array();
    $view_route_names['example_view.page_1'] = 'view.example_view.page_1';
    $this->state->expects($this->exactly(2))
      ->method('get')
      ->with('views.view_route_names')
      ->will($this->returnValue($view_route_names));

    $definitions = $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);
    $this->assertCount(1, $definitions);
    $plugin = $definitions['view.example_view.page_1'];
    $this->assertEquals('view.example_view.page_1', $plugin['route_name']);
    $this->assertEquals(12, $plugin['weight']);
    $this->assertEquals('Example title', $plugin['title']);
    $this->assertEquals($this->baseDefinition['class'], $plugin['class']);
    $this->assertEquals('views_view:view.example_view.page_1', $plugin['tab_root_id']);

    // Setup the prefix of the derivative.
    $definitions['views_view:view.example_view.page_1'] = $definitions['view.example_view.page_1'];
    unset($definitions['view.example_view.page_1']);
    $this->localTaskDerivative->alterLocalTasks($definitions);

    $plugin = $definitions['views_view:view.example_view.page_1'];
    $this->assertCount(1, $definitions);
    $this->assertEquals('view.example_view.page_1', $plugin['route_name']);
    $this->assertEquals(12, $plugin['weight']);
    $this->assertEquals('Example title', $plugin['title']);
    $this->assertEquals($this->baseDefinition['class'], $plugin['class']);
    $this->assertEquals('views_view:view.example_view.page_1', $plugin['tab_root_id']);
  }

  /**
   * Tests fetching the derivatives on a view with a local task and a parent.
   *
   * The parent is defined by another module, not views.
   */
  public function testGetDerivativeDefinitionsWithExistingLocalTask() {
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->getMock();
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->any())
      ->method('id')
      ->will($this->returnValue('example_view'));
    $executable->storage = $storage;

    $display_plugin = $this->getMockBuilder('Drupal\views\Plugin\views\display\PathPluginBase')
      ->setMethods(array('getOption', 'getPath'))
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
    $display_plugin->expects($this->exactly(2))
      ->method('getOption')
      ->with('menu')
      ->will($this->returnValue(array('type' => 'tab', 'weight' => 12, 'title' => 'Example title')));
    $display_plugin->expects($this->once())
      ->method('getPath')
      ->will($this->returnValue('path/example'));
    $executable->display_handler = $display_plugin;

    $result = array(array($executable, 'page_1'));
    $this->localTaskDerivative->setApplicableMenuViews($result);

    // Mock the view route names state.
    $view_route_names = array();
    $view_route_names['example_view.page_1'] = 'view.example_view.page_1';
    $this->state->expects($this->exactly(2))
      ->method('get')
      ->with('views.view_route_names')
      ->will($this->returnValue($view_route_names));

    // Mock the route provider.
    $route_collection = new RouteCollection();
    $route_collection->add('test_route', new Route('/path'));
    $this->routeProvider->expects($this->any())
      ->method('getRoutesByPattern')
      ->with('/path')
      ->will($this->returnValue($route_collection));

    // Setup the existing local task of the test_route.
    $definitions['test_route_tab'] = $other_tab = array(
      'route_name' => 'test_route',
      'title' => 'Test route',
      'tab_root_id' => 'test_route_tab',
    );

    $definitions += $this->localTaskDerivative->getDerivativeDefinitions($this->baseDefinition);

    // Setup the prefix of the derivative.
    $definitions['views_view:view.example_view.page_1'] = $definitions['view.example_view.page_1'];
    unset($definitions['view.example_view.page_1']);
    $this->localTaskDerivative->alterLocalTasks($definitions);

    $plugin = $definitions['views_view:view.example_view.page_1'];
    $this->assertCount(2, $definitions);

    // Ensure the other local task was not changed.
    $this->assertEquals($other_tab, $definitions['test_route_tab']);

    $this->assertEquals('view.example_view.page_1', $plugin['route_name']);
    $this->assertEquals(12, $plugin['weight']);
    $this->assertEquals('Example title', $plugin['title']);
    $this->assertEquals($this->baseDefinition['class'], $plugin['class']);
    $this->assertEquals('test_route_tab', $plugin['tab_root_id']);
  }

}

/**
 * Replaces the applicable views call for easier testability.
 */
class TestViewsLocalTask extends ViewsLocalTask {

  /**
   * Sets applicable views result.
   */
  public function setApplicableMenuViews($result) {
    $this->result = $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getApplicableMenuViews() {
    return $this->result;
  }

}
