<?php
/**
 * Created by PhpStorm.
 * User: jam
 * Date: 18.6.15
 * Time: 12:17
 */

namespace Trejjam\PresenterTree;


use Nette,
	Trejjam;

interface Exception
{
	
}

class InvalidArgumentException extends \InvalidArgumentException implements Exception
{
}

class DomainException extends \DomainException implements Exception
{
}

class LogicException extends \LogicException implements Exception
{
}

class RuntimeException extends \RuntimeException implements Exception
{
}
