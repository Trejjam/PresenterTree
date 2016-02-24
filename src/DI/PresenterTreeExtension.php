<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 3.1.16
 * Time: 5:02
 */

namespace Trejjam\PresenterTree\DI;

use Nette,
	Trejjam;

class PresenterTreeExtension extends Trejjam\BaseExtension\DI\BaseExtension
{
	protected $default = [
		'cacheNamespace'            => 'presenterTree',
		'robotLoaderCacheDirectory' => '%tempDir%/cache',
		'robotLoaderDirectories'    => [
			'%appDir%',
		],
		'excluded'                  => [
			'modules'    => [],
			'presenters' => [],
		],
	];

	protected $classesDefinition = [
		'presenterTree' => 'Trejjam\PresenterTree\PresenterTree',
		'robotLoader'   => 'Trejjam\PresenterTree\RobotLoader',
		'cache'         => 'Nette\Caching\Cache',
	];

	protected $factoriesDefinition = [
		'presenterInfoFactory' => 'Trejjam\PresenterTree\IPresenterInfoFactory',
	];

	public function beforeCompile()
	{
		parent::beforeCompile();

		$config = $this->createConfig();

		/** @var Nette\DI\ServiceDefinition[] $classes */
		$classes = $this->getClasses();
		$classes['cache']
			->setFactory('Nette\Caching\Cache')
			->setArguments(['@cacheStorage', $config['cacheNamespace']])
			->setAutowired(FALSE);

		$classes['robotLoader']->setArguments(
			[
				new Nette\Caching\Storages\FileStorage($config['robotLoaderCacheDirectory']),
			]
		);
		foreach ($config['robotLoaderDirectories'] as $v) {
			$classes['robotLoader']->addSetup('$service->addDirectory(?)', [$v]);
		}

		$classes['robotLoader']->setAutowired(FALSE);

		$classes['presenterTree']->setArguments(
			[
				$this->prefix('@cache'),
				$this->prefix('@robotLoader'),
			]
		);

		$classes['presenterTree']->addSetup('$service->setExcludedModules(?)', [$config['excluded']['modules']]);
		$classes['presenterTree']->addSetup('$service->setExcludedPresenters(?)', [$config['excluded']['presenters']]);
	}
}
