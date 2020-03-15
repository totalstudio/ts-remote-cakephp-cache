<?php

namespace Cache\Test\TestCase\Routing\Middleware;

use Cache\Routing\Middleware\CacheMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest as Request;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use TestApp\Http\TestRequestHandler;

class CacheMiddlewareTest extends TestCase {

	/**
	 * Teardown
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function testBasicRequest() {
		$request = ServerRequestFactory::fromGlobals();
		$response = new Response();

		$handler = new TestRequestHandler(null, $response);
		$middleware = new CacheMiddleware();
		$newResponse = $middleware->process($request, $handler);

		$this->assertSame($response, $newResponse, (string)$response . ' vs ' . (string)$newResponse);
		$this->assertSame('text/html', $newResponse->getType());
	}

	/**
	 * Tests setting parameters
	 *
	 * @return void
	 */
	public function testBasicUrlWithExt() {
		$folder = CACHE . 'views' . DS;
		$file = $folder . 'testcontroller-testaction-params1-params2-json.html';
		$content = '<!--cachetime:0;ext:json-->Foo bar';
		file_put_contents($file, $content);

		$request = ServerRequestFactory::fromGlobals([
			'REQUEST_URI' => '/testcontroller/testaction/params1/params2.json',
			'REQUEST_METHOD' => 'GET',
		]);
		$this->assertTrue($request->is('get'));
		$response = new Response();

		$handler = new TestRequestHandler(null, $response);
		$middleware = new CacheMiddleware();
		$newResponse = $middleware->process($request, $handler);

		$result = (string)$newResponse->getBody();
		$expected = 'Foo bar';
		$this->assertEquals($expected, $result);

		$result = $newResponse->getType();
		$expected = 'application/json';
		$this->assertEquals($expected, $result);

		$result = $newResponse->getHeaders();
		$this->assertNotEmpty($result['Expires']); // + 1 day

		unlink($file);
	}

	/**
	 * Tests that post skips
	 *
	 * @return void
	 */
	public function testBasicUrlWithExtPost() {
		$folder = CACHE . 'views' . DS;
		$file = $folder . 'testcontroller-testaction-params1-params2-json.html';
		$content = '<!--cachetime:0;ext:json-->Foo bar';
		file_put_contents($file, $content);

		$request = ServerRequestFactory::fromGlobals([
			'REQUEST_URI' => '/testcontroller/testaction/params1/params2.json',
			'REQUEST_METHOD' => 'POST',
		]);
		$this->assertTrue($request->is('post'));
		$response = new Response();

		$middleware = new CacheMiddleware([
			'when' => function (Request $request, Response $response) {
				return $request->is('get');
			},
		]);
		$handler = new TestRequestHandler(null, $response);
		$newResponse = $middleware->process($request, $handler);

		$this->assertSame('text/html', $newResponse->getType());
		$this->assertSame('', (string)$newResponse->getBody());
	}

	/**
	 * Test query strings
	 *
	 * @return void
	 */
	public function testQueryStringAndCustomTime() {
		$folder = CACHE . 'views' . DS;
		$file = $folder . 'posts-home-coffee-life-sleep-sissies.html';
		$content = '<!--cachetime:' . (time() + WEEK) . ';ext:html-->Foo bar';
		file_put_contents($file, $content);

		Router::reload();
		Router::connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
		Router::connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);
		Router::connect('/:controller/:action/*');

		$query = [
			'coffee' => 'life',
			'sleep' => 'sissies',
		];
		$request = new Request([
			'url' => '/posts/home?' . http_build_query($query),
			'environment' => [
				'REQUEST_METHOD' => 'GET',
			],
		]);
		$response = new Response();

		$handler = new TestRequestHandler(null, $response);
		$middleware = new CacheMiddleware();
		$newResponse = $middleware->process($request, $handler);

		$result = $newResponse->getBody();
		$expected = '<!--created:';
		$this->assertTextStartsWith($expected, $result);
		$expected = '-->Foo bar';
		$this->assertTextEndsWith($expected, $result);

		$result = $newResponse->getType();
		$expected = 'text/html';
		$this->assertEquals($expected, $result);

		$result = $newResponse->getHeaders();
		$this->assertNotEmpty($result['Expires']); // + 1 week

		unlink($file);
	}

}
