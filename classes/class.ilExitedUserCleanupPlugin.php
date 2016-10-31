<?php
require_once("Services/Cron/classes/class.ilCronHookPlugin.php");

class ilExitedUserCleanupPlugin extends ilCronHookPlugin {
	public function getPluginName() {
		return "ExitedUserCleanup";
	}

	function getCronJobInstances() {
		require_once $this->getDirectory()."/classes/class.ilExitedUserCleanupJob.php";
		$job = new ilExitedUserCleanupJob();
		return array($job);
	}

	function getCronJobInstance($a_job_id) {
		require_once $this->getDirectory()."/classes/class.ilExitedUserCleanupJob.php";
		return new ilExitedUserCleanupJob();
	}

	/**
	 * Get a closure to get txts from plugin.
	 *
	 * @return \Closure
	 */
	public function txtClosure() {
		return function($code) {
			return $this->txt($code);
		};
	}

	/**
	 * Get the ilActions
	 *
	 * @return ilActions
	 */
	public function getActions() {
		if($this->actions === null) {
			global $ilDB, $ilLog;
			require_once $this->getDirectory()."/classes/ilActions.php";
			$this->actions = new ilActions($ilDB, $ilLog);
		}

		return $this->actions;
	}
}