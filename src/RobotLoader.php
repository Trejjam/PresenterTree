<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 24.2.16
 * Time: 12:30
 */

namespace Trejjam\PresenterTree;

use Nette,
	App,
	Trejjam;

class RobotLoader extends Nette\Loaders\RobotLoader
{
	/**
	 * @var Nette\Caching\IStorage
	 */
	protected $cacheStorage;

	protected $classes = NULL;

	public function __construct(Nette\Caching\IStorage $cacheStorage)
	{
		parent::__construct();

		$this->cacheStorage = $cacheStorage;
	}

	public function getClasses()
	{
		if (is_null($this->classes)) {
			$this->setCacheStorage($this->cacheStorage);

			$this->classes = $this->getCache()->load($this->getKey());
		}

		return $this->classes;
	}

	/**
	 * @return array of class => filename
	 */
	public function getIndexedClasses()
	{
		$res = [];
		foreach ($this->getClasses() as $info) {
			if (is_array($info)) {
				$res[$info['orig']] = $info['file'];
			}
		}

		return $res;
	}
}
