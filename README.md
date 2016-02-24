Presenter tree
=====

[![Latest stable](https://img.shields.io/packagist/v/trejjam/presenter-tree.svg)](https://packagist.org/packages/trejjam/utils)

This library is based on gist: https://gist.github.com/fprochazka/705975

Installation
------------

The best way to install Trejjam/PresenterTree is using  [Composer](http://getcomposer.org/):

```sh
$ composer require trejjam/presenter-tree
```

Configuration
-------------

.neon
```yml
extensions:
	presenterTree: Trejjam\PresenterTree\DI\PresenterTreeExtension
```

Usage
-----

Presenter/Model:

```php
	use \Trejjam\PresenterTree;

	/**
	* @var PresenterTree\PresenterTree @inject
	*/
	public $presenterTree;
	
	function renderDefault() {

	}
```
