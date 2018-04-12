<?php
declare(strict_types=1);

namespace Trejjam\PresenterTree;

interface IPresenterInfoFactory
{
	public function create(
		string $presenter,
		string $module,
		string $class,
		callable $getActionCallback
	) : PresenterInfo;
}
