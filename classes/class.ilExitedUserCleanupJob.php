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

			foreach($actions->getBookedOrWaitingCourseFor($usr_id) as $crs_id) {
				$return = $action->cancelCourse($crs_id, $usr_id);

				switch($return) {
					case ilActions::CRS_NO_START_DATE:
						$ilLog->write("gevExitedUserCleanupJob: User $usr_id was not removed from training $crs_id, since"
							." the start date of the training could not be determined.");
						break;
					case ilActions::CRS_START_DATE_EXPIRED:
						$ilLog->write("gevExitedUserCleanupJob: User $usr_id was not removed from training $crs_id, since"
							." training start date expired: ".$start_date->get(IL_CAL_DATE)." < ".$now);
						break;
					case ilActions::CRS_CANCELD:
						$actions->sendExitMail($crs_id, $usr_id);
						$ilLog->write("gevExitedUserCleanupJob: User $usr_id was canceled from training $crs_id.");
						break;
				}
			}
			ilCronManager::ping($this->getId());

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

			try {
				$nas = $actions->getNaOf();
				foreach ($nas as $na) {
					$actions->assignNaToNoAdviser($na);
					$ilLog->write("gevExitedUserCleanupJob: Moved na $na of user $usr_id to no-adviser-OrgUnit.");
				}
				if (count($nas) > 0) {
					$actions->removeNAOrgUnitOf($usr_id);
					$ilLog->write("gevExitedUserCleanupJob: Removed NA-OrgUnit of $usr_id.");
				}
			}
			catch (Exception $e) {
				$ilLog->write("gevExitedUserCleanupJob: ".$e);
			}

			try {
				if($actions->setWBDActionToExit($usr_id)) {
					$ilLog->write("gevExitedUserCleanupJob: Set next wbd action to release for user: ".$usr_id.".");
				}
			}catch (Exception $e) {
				$ilLog->write("gevExitedUserCleanupJob: ".$e);
			}

			//update user and create a history entry
			$usr->read();
			$usr->setActive(false);
			$usr->update();
			
			// i'm alive!
			ilCronManager::ping($this->getId());
		}

		$ilLog->write("gevExitedUserCleanupJob: purging empty na-org units.");
		$actions->purgeEmptyNaOrgu();

		$cron_result->setStatus(ilCronJobResult::STATUS_OK);
		return $cron_result;
	}
}