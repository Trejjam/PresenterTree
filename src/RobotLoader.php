<?php
declare(strict_types=1);

namespace Trejjam\PresenterTree;

use Nette;
use Trejjam;

class RobotLoader extends Nette\Loaders\RobotLoader
{
	/**
	 * @var Nette\Caching\IStorage
	 */
	protected $cacheStorage;
	/**
	 * @var string
	 */
	private $tempDirectory;

	protected $classes = NULL;

	public function __construct(string $tempDirectory)
	{
		parent::__construct();

		$this->tempDirectory = $tempDirectory;

		$this->setTempDirectory($this->tempDirectory);
	}

	/**
	 * @return array of class => filename
	 */
	public function getIndexedClasses() : array
	{
		$classes = parent::getIndexedClasses();

		if (count($classes) === 0) {
			$loadCache = new \ReflectionMethod(Nette\Loaders\RobotLoader::class, 'loadCache');
			$loadCache->setAccessible(TRUE);
			$loadCache->invoke($this);

			$classes = parent::getIndexedClasses();
		}

		return $classes;
	}
}
