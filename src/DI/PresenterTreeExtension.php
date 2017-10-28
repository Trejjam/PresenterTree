<?php
declare(strict_types=1);

namespace Trejjam\PresenterTree\DI;

use Nette;
use Trejjam;

class PresenterTreeExtension extends Trejjam\BaseExtension\DI\BaseExtension
{
	protected $default = [
		'cacheNamespace'            => 'presenterTree',
		'robotLoaderCacheDirectory' => 'cache',
		'robotLoaderDirectories'    => [],
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

	public function loadConfiguration(bool $validateConfig = TRUE) : void
	{
		$this->default['robotLoaderCacheDirectory'] = $this->getContainerBuilder()->parameters['tempDir'] . DIRECTORY_SEPARATOR . $this->default['robotLoaderCacheDirectory'];
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
				new Nette\Caching\Storages\FileStorage($this->config['robotLoaderCacheDirectory']),
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

		$types['presenterTree']->addSetup('$service->setExcludedModules(?)', [$this->config['excluded']['modules']]);
		$types['presenterTree']->addSetup('$service->setExcludedPresenters(?)', [$this->config['excluded']['presenters']]);
	}
}
