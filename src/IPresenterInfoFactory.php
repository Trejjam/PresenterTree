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

interface IPresenterInfoFactory
{
	/**
	 * @param string   $presenter
	 * @param string   $module
	 * @param string   $class
	 * @param callable $getActionCallback
	 *
	 * @return PresenterInfo
	 */
	public function create($presenter, $module, $class, callable $getActionCallback);
}
