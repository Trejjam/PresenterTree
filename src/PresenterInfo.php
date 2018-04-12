<?php
declare(strict_types=1);

namespace Trejjam\PresenterTree;

use Trejjam;
use Nette\Reflection;


/**
 * @author Filip Procházka <hosiplan@kdyby.org>
 *
 * @method getActionCallback(PresenterInfo $presenterInfo)
 */
class PresenterInfo
{
	/**
	 * @var string
	 */
	protected $presenter;
	/**
	 * @var string
	 */
	protected $module;
	/**
	 * @var string
	 */
	protected $class;
	/**
	 * @var callable
	 */
	protected $getActionCallback;

	/**
	 * @var string
	 */
	protected $actions = NULL;

	public function __construct(
		string $presenter,
		string $module,
		string $class,
		callable $getActionCallback
	) {
		$this->presenter = $presenter;
		$this->module = $module;
		$this->class = $class;
		$this->getActionCallback = $getActionCallback;
	}

	public function setGetActionCallback(callable $getActionCallback)
	{
		$this->getActionCallback = $getActionCallback;
	}

	public function isPublic() : bool
	{
		$ref = $this->getPresenterReflection();

		return !$ref->hasAnnotation('hideInTree');
	}

	public function getPresenterReflection() : Reflection\ClassType
	{
		return new Reflection\ClassType($this->getPresenterClass());
	}

	public function getActions() : array
	{
		if ($this->actions === NULL) {
			$this->actions = call_user_func_array($this->getActionCallback, [$this]);
		}

		return $this->actions;
	}

	public function getPresenterName(bool $full = FALSE) : string
	{
		return ($full ? ':' . $this->module . ':' : NULL) . $this->presenter;
	}

	public function getPresenterClass() : string
	{
		return $this->class;
	}

	public function getModule() : string
	{
		return $this->module;
	}

	public function __toString() : string
	{
		return $this->getPresenterName(TRUE);
	}

	public function __sleep() : array
	{
		return [
			'presenter',
			'module',
			'class',
		];
	}
}
