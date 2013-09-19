# Plugin architecture for php-resque

## Usage

TODO..

### Creating a Plugin

Any class with one of following methods

* `beforePerform`
* `afterPerform`
* `onFailure`

### Using a Plugin

In the job you want to use, define a public static `resque_plugins` property as an array of Plugin classes you want to use.

```php
<?php

class TestJob {

	public static $resque_plugins = array(
		'Resque\Plugin\Retry'
	);

	public function peform() {
		// perform code
	}
}