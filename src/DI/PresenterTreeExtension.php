<?php
declare(strict_types=1);

namespace Trejjam\PresenterTree\DI;

use Nette;
use Trejjam;

class PresenterTreeExtension extends Trejjam\BaseExtension\DI\BaseExtension
{
	protected $default = [
		'cacheNamespace'            => 'presenterTree',
		'robotLoaderCacheDirectory' => 'cache/Nette.RobotLoader',
		'robotLoaderDirectories'    => [],
		'excluded'                  => [
			'module'    => [],
			'presenter' => [],
		],
	];

	protected $classesDefinition = [
		'presenterTree' => Trejjam\PresenterTree\PresenterTree::class,
		'robotLoader'   => Trejjam\PresenterTree\RobotLoader::class,
		'cache'         => Nette\Caching\Cache::class,
	];

	protected $factoriesDefinition = [
		'presenterInfoFactory' => 'Trejjam\PresenterTree\IPresenterInfoFactory',
	];

	public function loadConfiguration(bool $validateConfig = TRUE) : void
	{
		$this->default['robotLoaderCacheDirectory'] = implode(
			DIRECTORY_SEPARATOR,
			[
				$this->getContainerBuilder()->parameters['tempDir'],
				$this->default['robotLoaderCacheDirectory'],
			]
		);
		$this->default['robotLoaderDirectories'][] = $this->getContainerBuilder()->parameters['appDir'];

		parent::loadConfiguration($validateConfig);
	}

	public function beforeCompile()
	{
		parent::beforeCompile();

		/** @var Nette\DI\ServiceDefinition[] $types */
		$types = $this->getTypes();
		$types['cache']
			->setFactory('Nette\Caching\Cache')
			->setArguments(['@cacheStorage', $this->config['cacheNamespace']])
			->setAutowired(FALSE);

		$types['robotLoader']->setArguments(
			[
				$this->config['robotLoaderCacheDirectory'],
			]
		);
		foreach ($this->config['robotLoaderDirectories'] as $v) {
			$types['robotLoader']->addSetup('$service->addDirectory(?)', [$v]);
		}

		$types['robotLoader']->setAutowired(FALSE);

		$types['presenterTree']->setArguments(
			[
				$this->prefix('@cache'),
				$this->prefix('@robotLoader'),
			]
		);

		$types['presenterTree']->addSetup('$service->setExcludedModule(?)', [$this->config['excluded']['module']]);
		$types['presenterTree']->addSetup('$service->setExcludedPresenter(?)', [$this->config['excluded']['presenter']]);
	}
}
