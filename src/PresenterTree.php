<?php

namespace Trejjam\PresenterTree;

use Nette;
use Nette\Reflection;
use Nette\Utils;

/**
 * @author Filip Procházka <hosiplan@kdyby.org>
 */
class PresenterTree
{
	const ALL        = TRUE;
	const DIRECT     = FALSE;
	const ALL_MODULE = '__all__';

	/**
	 * @var Nette\Caching\Cache
	 */
	protected $cache;
	/**
	 * @var RobotLoader
	 */
	protected $robotLoader;
	/**
	 * @var IPresenterInfoFactory
	 */
	protected $presenterInfoFactory;
	/**
	 * @var Nette\Application\IPresenterFactory|Nette\Application\PresenterFactory
	 */
	protected $presenterFactory;

	/**
	 * @var array
	 */
	protected $excludedPresenters = [];
	/**
	 * @var array
	 */
	protected $excludedModules = [];

	protected $isLoaded = FALSE;

	public function __construct(
		Nette\Caching\Cache $cache,
		RobotLoader $robotLoader,
		IPresenterInfoFactory $presenterInfoFactory,
		Nette\Application\IPresenterFactory $presenterFactory
	) {
		$this->cache = $cache;
		$this->robotLoader = $robotLoader;
		$this->presenterInfoFactory = $presenterInfoFactory;
		$this->presenterFactory = $presenterFactory;

		$this->cache->clean([Nette\Caching\Cache::ALL]);
	}

	protected function load()
	{
		if ( !$this->isLoaded) {
			if ( !$this->isActual()) {
				$this->cache->save('presenters', $presenters = $this->buildPresenterTree());
				$this->cache->save('modules', $this->buildModuleTree($presenters));
				$this->cache->save('actions', $this->buildActionTree($presenters));
				$this->setActual();
			}

			$this->isLoaded = TRUE;
		}
	}

	public function setExcludedPresenters(array $presenters)
	{
		$this->excludedPresenters = $presenters;
	}

	public function setExcludedModules(array $modules)
	{
		$this->excludedModules = $modules;
	}

	protected function getHash()
	{
		$classes = $this->robotLoader->getIndexedClasses();

		return md5(serialize($classes));
	}

	/**
	 * @return bool
	 */
	protected function isActual()
	{
		if ($this->cache->load('hash') != $this->getHash()) {
			return FALSE;
		}

		return TRUE;
	}

	protected function setActual($isActual = TRUE)
	{
		return $this->cache->save('hash', $isActual ? $this->getHash() : NULL);
	}

	/**
	 * @return array
	 */
	protected function buildPresenterTree()
	{
		$classes = array_keys($this->robotLoader->getIndexedClasses());
		$tree = [];
		$modules = [];

		foreach ($i = new \RegexIterator(new \ArrayIterator($classes), "~.*Presenter$~") as $class) {
			$excluded = FALSE;
			$reflectionClass = new \ReflectionClass($class);
			foreach ($this->excludedPresenters as $v) {
				if ($reflectionClass->getName() == $v || is_subclass_of($class, $v)) {
					$excluded = TRUE;
				}
			}

			if ($excluded) {
				continue;
			}

			$nettePath = $this->presenterFactory->unformatPresenterClass($class);

			$nettePathArray = explode(':', $nettePath);
			$presenter = array_pop($nettePathArray);
			$module = implode(':', $nettePathArray);

			if (in_array($module, $this->excludedModules)) {
				continue;
			}
			$modules[$module] = TRUE;

			$presenterInfo = $this->presenterInfoFactory->create($presenter, $module, $class, [$this, 'getPresenterActions']);
			$reflection = $presenterInfo->getPresenterReflection();

			if ( !$reflection->isAbstract() && $presenterInfo->isPublic()) {
				$t =& $tree['byModule'];
				foreach ($nettePathArray as $step) {
					$t[$step] = isset($t[$step]) ? $t[$step] : [];
					$t =& $t[$step];
				}

				$t[$presenter] = $presenterInfo;

				$steps = [];
				if ($module != '') {
					foreach ($nettePathArray as $step) {
						$steps[] = $step;
						$module = substr($this->formatNettePath($steps), 1);
						$relative = substr($this->formatNettePath(array_diff($nettePathArray, $steps), $presenter), 1);

						$tree['all'][static::ALL_MODULE][$module . ':' . $relative] = $presenterInfo;
						$tree['all'][$module][$relative] = $presenterInfo;
					}
				}
				else {
					$tree['all'][static::ALL_MODULE][$presenter] = $presenterInfo;
					$tree['all'][$module][$presenter] = $presenterInfo;
				}
			}
		}

		ksort($tree['all']);
		ksort($tree['all'][static::ALL_MODULE]);
		foreach ($modules as $k => $v) {
			ksort($tree['all'][$k]);
		}


		return $tree;
	}

	/**
	 * @param $presenters
	 *
	 * @return array
	 */
	protected function buildModuleTree($presenters)
	{
		$tree = [];

		$modules = [];
		/**
		 * @var string        $fullPath
		 * @var PresenterInfo $presenter
		 */
		foreach ($presenters['all'][static::ALL_MODULE] as $fullPath => $presenter) {
			if ( !in_array($presenter->getModule(), $modules)) {
				$modules[] = $presenter->getModule();
			}
		}

		foreach ($modules as $module) {
			$nettePath = explode(':', $module);
			$module = array_pop($nettePath);

			$t =& $tree['byModule'];
			foreach ($nettePath as $step) {
				$t[$step] = isset($t[$step]) ? $t[$step] : [];
				$t =& $t[$step];
			}

			$t = is_array($t) ? $t : [];
			$t[] = $module;
		}

		return $tree;
	}

	/**
	 * @param $presenters
	 *
	 * @return array
	 */
	protected function buildActionTree($presenters)
	{
		$tree = [];

		/**
		 * @var string        $fullPath
		 * @var PresenterInfo $presenter
		 */
		foreach ($presenters['all'][static::ALL_MODULE] as $fullPath => $presenter) {
			$reflection = $presenter->getPresenterReflection();

			/** @var Nette\Application\UI\Presenter $presenterInstance */
			$presenterInstance = $this->presenterFactory->createPresenter(
				$this->presenterFactory->unformatPresenterClass($presenter->getPresenterClass())
			);
			$templateViewPattern = $presenterInstance->formatTemplateFiles();

			$views = [];
			foreach ($templateViewPattern as $pattern) {
				$filePattern = Utils\Strings::split(basename($pattern), '~\*~');
				if (is_dir(dirname($pattern))) {
					foreach (Utils\Finder::findFiles(basename($pattern))->in(dirname($pattern)) as $view) {
						$views[] = Utils\Strings::replace($view->getFilename(), [
							'~^' . preg_quote($filePattern[0]) . '~' => '',
							'~' . preg_quote($filePattern[1]) . '$~' => '',
						]);
					}
				}
			}

			$actions = [];
			foreach ($views as $view) {
				$actions[$view] = $fullPath . ':' . lcfirst($view);
			}

			$methods = array_map(function ($method) {
				return $method->name;
			}, $reflection->getMethods(Reflection\Method::IS_PUBLIC));

			$methods = array_filter($methods, function ($method) {
				return in_array(substr($method, 0, 6), ['action', 'render']);
			});

			$allowed = [];
			foreach ($methods as $method) {
				$method = $reflection->getMethod($method);
				$action = lcfirst(substr($method->name, 6));

				if ( !$method->hasAnnotation('hideInTree')) {
					if ( !isset($allowed[$action])) {
						$allowed[$action] = $fullPath . ':' . $action;
					}

				}
				else {
					$allowed[$action] = FALSE;
				}
			}

			$actions = array_filter(array_merge($actions, $allowed), function ($action) {
				return (bool)$action;
			});

			if ($actions) {
				$tree['byPresenterClass'][$presenter->getPresenterClass()] = array_flip($actions);

				$t =& $tree['byModule'];
				foreach (Utils\Strings::split($presenter->getModule(), '~:~') as $step) {
					$t[$step] = isset($t[$step]) ? $t[$step] : [];
					$t =& $t[$step];
				}

				$t[$presenter->getPresenterName()] = array_flip($actions);
			}
		}

		return $tree;
	}

	/**
	 * @param string $module
	 *
	 * @return array
	 */
	public function getPresenters($module = self::ALL_MODULE)
	{
		$this->load();

		$presenters = $this->cache->load('presenters');

		/** @var PresenterInfo $v */
		foreach ($presenters['all'][$module] as &$v) {
			$v->setGetActionCallback([$this, 'getPresenterActions']);
		}

		return isset($presenters['all'][$module]) ? $presenters['all'][$module] : NULL;
	}

	/**
	 * @param PresenterInfo $presenter
	 *
	 * @return array
	 */
	public function getPresenterActions(PresenterInfo $presenter)
	{
		$this->load();

		return $this->cache->load('actions')['byPresenterClass'][$presenter->getPresenterClass()];
	}

	/**
	 * @param string $nettePath
	 *
	 * @return array
	 */
	public function getModules($nettePath = NULL)
	{
		$this->load();

		$nettePath = trim($nettePath, ':');

		if ( !$nettePath) {
			return array_filter($this->cache->load('modules')['byModule'], function ($item) {
				return !is_array($item);
			});
		}

		$tree = $this->cache->load('modules')['byModule'];
		foreach (Utils\Strings::split($nettePath, '~:~') as $step) {
			if ( !isset($tree[$step])) {
				return NULL;
			}

			$tree =& $tree[$step];
		}

		return array_filter($tree, function ($item) {
			return !is_array($item);
		});
	}


	/**
	 * @param array  $steps
	 * @param string $presenter
	 *
	 * @return string
	 */
	protected function formatNettePath(array $steps, $presenter = NULL)
	{
		return '' . ($steps ? ':' . implode(':', $steps) : NULL) . ($presenter ? ':' . $presenter : NULL);
	}
}