<?php
require_once("Services/Cron/classes/class.ilCronManager.php");
require_once("Services/Cron/classes/class.ilCronJob.php");
require_once("Services/Cron/classes/class.ilCronJobResult.php");
require_once(__DIR__."/ilActions.php");

class ilExitedUserCleanupJob extends ilCronJob {
	public function __construct() {
		$this->plugin =
			ilPlugin::getPluginObject(IL_COMP_SERVICE, "Cron", "crnhk",
				ilPlugin::lookupNameForId(IL_COMP_SERVICE, "Cron", "crnhk", $this->getId()));
	}

	public function getId() {
		return "exitedusercleanup";
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle() {
		return $this->plugin->txt("title");
	}

	/**
	 * @inheritdoc
	 */
	public function getDescription() {
		return $this->plugin->txt("description");
	}

	public function hasAutoActivation() {
		return true;
	}

	public function hasFlexibleSchedule() {
		return false;
	}

	public function getDefaultScheduleType() {
		return ilCronJob::SCHEDULE_TYPE_DAILY;
	}

	public function getDefaultScheduleValue() {
		return 1;
	}

	public function run() {
		global $ilLog;

		$actions = $this->plugin->getActions();
		$cron_result = new ilCronJobResult();
		foreach($actions->getActiveExitedUser() as $usr_id) {
			$usr = $actions->getUserObject($usr_id);

			$usr->setActive(false);
			$ilLog->write("gevExitedUserCleanupJob: Deactivated user with id $usr_id.");
			ilCronManager::ping($this->getId());

			foreach($actions->getUserOrgUnits($usr_id) as $orgu_id) {
				$actions->deassignOrgUnit($usr_id, $orgu_id);
				$ilLog->write("gevExitedUserCleanupJob: Removed user with id $usr_id from OrgUnit with id $orgu_id.");
			}
			ilCronManager::ping($this->getId());

			$actions->assignToExitOrgunit($usr_id);
			$ilLog->write("gevExitedUserCleanupJob: Moved user with id $usr_id to exit-OrgUnit.");
			ilCronManager::ping($this->getId());

			//update user and create a history entry
			$usr->read();
			$usr->setActive(false);
			$usr->update();

			// i'm alive!
			ilCronManager::ping($this->getId());
		}

		$cron_result->setStatus(ilCronJobResult::STATUS_OK);
		return $cron_result;
	}
}