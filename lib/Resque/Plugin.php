<?php

namespace Resque;
use \Resque_Event;
use \Resque_Job;
use \Exception;

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
			Resque_Event::listen($hook, function($payload) use ($notifyMethod, $hook) {
				call_user_func($notifyMethod, $hook, $payload);
			});
		}
	}

	/**
	 * @param  string 	$hook 			which hook to run
	 * @param  mixed 	$jobOrFailure 	job for which to run the plugins
	 */
	public static function notify_plugins($hook, $jobOrFailure, $job = null) {
		if ($jobOrFailure instanceof Resque_Job) {
			$job = $jobOrFailure;
			$exception = null;
		} elseif ($jobOrFailure instanceof Exception) {
			$exception = $jobOrFailure;
		} else {
			// TODO: review this choice, not sure if it's the right thing to do
			return; // fail silently if we don't know how to handle this
		}

		$plugins = static::plugins($job, $hook);

		foreach ($plugins as $plugin) {
			$callable = array($plugin, $hook);
			if (is_callable($callable)) {
				$payload = array($job, $job->getInstance());
				
				if (!is_null($exception)) {
					array_push($payload, $exception);
				}

				call_user_func_array($callable, $payload);
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