<?php

namespace Resque;
use \Resque_Event;

class Plugin {

	/**
	 * @var  array  of plugin instances, ordered by job 
	 */
	protected static $_pluginInstances = array();

	/**
	 * @var  array  of hooks to listen for
	 */
	protected static $_hooks = array(
		'beforePerform',
		'afterPerform',
		'onFailure'
	);

	/**
	 * Start listening to Resque_Event and start using those plugis
	 */
	public static function initialize() {
		$hooks = static::$_hooks;
		$class = get_called_class();
		$notifyMethod = $class ."::notify_plugins";

		foreach ($hooks as $hook) {
			Resque_Event::listen($hook, $notifyMethod);
		}
	}

	/**
	 * @param  Resque_Job 	$job 	job for which to run the plugins
	 * @param  string 		$hook 	which hook to run
	 */
	public static function notify_plugins(Resque $job, $hook) {
		$plugins = static::plugins($job, $hook);

		foreach ($plugins as $plugin) {
			if (is_callable($plugin)) {
				call_user_func($plugin, $job, $hook);
			}
		}
	} 

	/**
	 * Retrieve the plugin instances for this job, optionally filtered by a hook
	 * 
	 * @param  Resque_Job 	$job  	an instance of a job
	 * @param  string 		$hook 	optional hook to filter by
	 * @return array 	of plugins for the job
	 */
	public static function plugins(Resque_Job $job, $hook = null) {
		$jobClass = $job->getClass();

		if (empty(static::$_pluginInstances[$jobClass])) {
			static::$_pluginInstances = static::createInstances($jobClass);
		}

		$instances = static::$_pluginInstances[$jobClass];

		if (empty($hook) or empty($instances)) {
			return $instances;
		}

		return array_filter($instances, function($instance) use ($hook) {
			return is_callable(array($instance, $hook));
		});
	}

	/**
	 * Create instances of the plugins for the specified job class
	 * @param 	string $jobClass 
	 * @return  array  of plugin instances for this job class
	 */
	public static function createInstances($jobClass) {
		$instances = array();

		if (property_exists($jobClass, 'resquePlugins')) {
			$pluginClasses = $jobClass::$resquePlugins;

			foreach ($pluginClasses as $pluginClass) {
				if (class_exists($pluginClass)) {
					array_push($instances, new $pluginClass);
				}
			}
		}

		return $instances;
	}


}