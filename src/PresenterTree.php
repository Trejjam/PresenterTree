<?php
declare(strict_types=1);

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
	protected $excludedPresenter = [];
	/**
	 * @var array
	 */
	protected $excludedModule = [];

	protected $isLoaded = FALSE;

	protected $presenterCache = [];
	protected $actionCache    = [];

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
	}

	protected function load()
	{
		if ( !$this->isLoaded) {
			$this->presenterCache = $this->cache->load(
				'presenters',
				function () {
					$presenters = $this->buildPresenterTree();

					$this->cache->save('actions', $this->buildActionTree($presenters));

					return $presenters;
				}
			);
			$this->actionCache = $this->cache->load('actions');

			$this->isLoaded = TRUE;
		}
	}

	public function setExcludedPresenter(array $presenters)
	{
		$this->excludedPresenter = $presenters;
	}

	public function setExcludedModule(array $modules)
	{
		$this->excludedModule = $modules;
	}

	protected function buildPresenterTree() : array
	{
		$classes = array_keys($this->robotLoader->getIndexedClasses());
		$tree = [
			'all'      => [],
			'byModule' => [],
		];
		$modules = [];

		$iterator = new \RegexIterator(new \ArrayIterator($classes), '~.*Presenter$~');
		foreach ($iterator as $_class) {
			$excluded = FALSE;
			$reflectionClass = new \ReflectionClass($_class);
			foreach ($this->excludedPresenter as $_excludedPresenter) {
				if (
					$reflectionClass->getName() === $_excludedPresenter
					|| is_subclass_of($_class, $_excludedPresenter)
				) {
					$excluded = TRUE;
				}
			}

			if ($excluded) {
				continue;
			}

			$nettePath = $this->presenterFactory->unformatPresenterClass($_class);

			$nettePathArray = explode(':', $nettePath);
			$presenter = array_pop($nettePathArray);
			$module = implode(':', $nettePathArray);


			foreach ($this->excludedModule as $_excludedModule) {
				if (
					$_excludedModule === $module
					|| Utils\Strings::startsWith($module, $_excludedModule . ':')
				) {
					$excluded = TRUE;
					break;
				}
			}

			if ($excluded) {
				continue;
			}

			$modules[$module] = TRUE;

			$presenterInfo = $this->presenterInfoFactory->create($presenter, $module, $_class, [$this, 'getPresenterActions']);
			$reflection = $presenterInfo->getPresenterReflection();

			if (
				!$reflection->isAbstract()
				&& $presenterInfo->isPublic()
			) {
				$_module =& $tree['byModule'];
				if (count($nettePathArray) === 0) {
					$nettePathArray[] = '';
				}
				foreach ($nettePathArray as $step) {
					$_module[$step] = $_module[$step] ?? [];
					$_module =& $_module[$step];
				}

				$_module[$presenter] = $presenterInfo;
				unset($_module);

				$steps = [];
				if ($module !== '') {
					foreach ($nettePathArray as $step) {
						$steps[] = $step;
						$module = implode(':', $steps);
						$relative = implode(
								':',
								array_diff($nettePathArray, $steps)
							) . ':' . $presenter;

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
		foreach (array_keys($modules) as $_moduleName) {
			ksort($tree['all'][$_moduleName]);
		}

		return $tree;
	}

	protected function buildActionTree(array $presenters) : array
	{
		$tree = [];

		foreach ($presenters['all'][static::ALL_MODULE] as $fullPath => $presenter) {
			assert($presenter instanceof PresenterInfo);

			$reflection = $presenter->getPresenterReflection();

			$presenterInstance = $this->presenterFactory->createPresenter(
				$this->presenterFactory->unformatPresenterClass($presenter->getPresenterClass())
			);

			if ( !($presenterInstance instanceof Nette\Application\UI\Presenter)) {
				continue;
			}

			$templateViewPattern = $presenterInstance->formatTemplateFiles();

			$views = [];
			foreach ($templateViewPattern as $pattern) {
				$filePattern = Utils\Strings::split(basename($pattern), '~\*~');
				if (is_dir(dirname($pattern))) {
					/** @var \SplFileInfo $viewFile */
					foreach (
						Utils\Finder::findFiles(
							basename($pattern)
						)->in(
							dirname($pattern)
						) as $viewFile
					) {
						$views[] = Utils\Strings::replace($viewFile->getFilename(), [
							'~^' . preg_quote($filePattern[0]) . '~' => '',
							'~' . preg_quote($filePattern[1]) . '$~' => '',
						]);
					}
				}
			}

			$allowed = [];
			$actions = [];
			foreach ($views as $_view) {
				$view = lcfirst($_view);
				$actions[$view] = $fullPath . ':' . $view;
				$allowed[$view] = TRUE;
			}

			$presenterMethods = [];
			foreach (
				$reflection->getMethods(Reflection\Method::IS_PUBLIC)
				as $presenterMethod
			) {
				if (in_array(
					substr($presenterMethod->name, 0, 6),
					['action', 'render']
				)) {
					$presenterMethods[$presenterMethod->name] = $presenterMethod;
				}
			}

			foreach ($presenterMethods as $_presenterMethod) {
				$action = lcfirst(substr($_presenterMethod->name, 6));

				if ($_presenterMethod->hasAnnotation('hideInTree')) {
					$allowed[$action] = FALSE;
				}
				else {
					$actions[$action] = $fullPath . ':' . $action;
					$allowed[$action] = TRUE;
				}
			}

			$allowedActions = array_filter($actions,
				function (string $action) use ($allowed) {
					return $allowed[$action];
				}, ARRAY_FILTER_USE_KEY
			);

			if (count($allowedActions) > 0) {
				$tree['byPresenterClass'][$presenter->getPresenterClass()] = array_flip($allowedActions);

				$_module =& $tree['byModule'];
				foreach (explode(':', $presenter->getModule()) as $step) {
					$_module[$step] = $_module[$step] ?? [];
					$_module =& $_module[$step];
				}

				$_module[$presenter->getPresenterName()] = array_flip($actions);
				unset($_module);
			}
			else {
				$tree['byPresenterClass'][$presenter->getPresenterClass()] = [];
			}
		}

		return $tree;
	}

	public function getPresenters(string $module = self::ALL_MODULE) : ?array
	{
		$this->load();

		foreach ($this->presenterCache['all'][$module] as $presenterInfo) {
			assert($presenterInfo instanceof PresenterInfo);

			$presenterInfo->setGetActionCallback([$this, 'getPresenterActions']);
		}

		return $this->presenterCache['all'][$module] ?? NULL;
	}

	public function getPresenterActions(PresenterInfo $presenter) : ?array
	{
		$this->load();

		return $this->actionCache['byPresenterClass'][$presenter->getPresenterClass()] ?? NULL;
	}
}
