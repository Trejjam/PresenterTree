<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 24.2.16
 * Time: 11:19
 */

namespace Trejjam\PresenterTree;

use Nette;
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

	/**
	 * @param string   $presenter
	 * @param string   $module
	 * @param string   $class
	 * @param callable $getActionCallback
	 */
	public function __construct($presenter, $module, $class, callable $getActionCallback)
	{
		$this->presenter = $presenter;
		$this->module = $module;
		$this->class = $class;
		$this->getActionCallback = $getActionCallback;
	}

	public function setGetActionCallback(callable $getActionCallback)
	{
		$this->getActionCallback = $getActionCallback;
	}

	/**
	 * @return bool
	 */
	public function isPublic()
	{
		$ref = $this->getPresenterReflection();

		return !$ref->hasAnnotation('hideInTree');
	}

	/**
	 * @return Reflection\ClassType
	 */
	public function getPresenterReflection()
	{
		return new Reflection\ClassType($this->getPresenterClass());
	}

	/**
	 * @return array
	 */
	public function getActions()
	{
		if ($this->actions === NULL) {
			$this->actions = call_user_func_array($this->getActionCallback, [$this]);
		}

		return $this->actions;
	}

	/**
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getPresenterName($full = FALSE)
	{
		return ($full ? ':' . $this->module . ':' : NULL) . $this->presenter;
	}

	/**
	 * @return string
	 */
	public function getPresenterClass()
	{
		return $this->class;
	}

	/**
	 * @return string
	 */
	public function getModule()
	{
		return $this->module;
	}

	public function __toString()
	{
		return $this->getPresenterName(TRUE);
	}

	public function __sleep()
	{
		return [
			'presenter',
			'module',
			'class',
		];
	}
}
