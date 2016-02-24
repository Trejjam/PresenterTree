<?php

namespace Test;

use Nette,
	Tester,
	Tester\Assert,
	Trejjam\PresenterTree;

$container = require __DIR__ . '/bootstrap.php';


class PresenterTreeTest extends Tester\TestCase
{
	private $container;

	function __construct(Nette\DI\Container $container)
	{
		$this->container = $container;
	}

	function testNumberAt()
	{
		
	}
}

$test = new PresenterTreeTest($container);
$test->run();
