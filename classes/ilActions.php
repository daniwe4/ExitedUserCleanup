<?php
require_once("Services/GEV/Utils/classes/class.gevSettings.php");
require_once("Services/GEV/Utils/classes/class.gevOrgUnitUtils.php");
require_once("Services/GEV/Utils/classes/class.gevCourseUtils.php");
require_once("Services/GEV/Utils/classes/class.gevObjectUtils.php");
require_once("Modules/OrgUnit/classes/class.ilObjOrgUnitTree.php");
require_once("Services/GEV/Mailing/classes/class.gevCrsAutoMails.php");

class ilActions {
	const CRS_NO_START_DATE = "no_start_date";
	const CRS_START_DATE_EXPIRED = "start_date_expired";
	const CRS_CANCELD = "crs_canceld";

	public function __construct($db, $log) {
		$this->db = $db;
		$this->log = $log;
		$this->settings = gevSettings::getInstance();
	}

	public function getActiveExitedUser() {
		$ret = array();

		$exit_udf_field_id = $this->settings->getUDFFieldId(gevSettings::USR_UDF_EXIT_DATE);

		$res = $this->getDB()->query("SELECT ud.usr_id, udf.value "
						   ."  FROM usr_data ud"
						   ."  JOIN udf_text udf "
						   ."    ON udf.usr_id = ud.usr_id"
						   ."   AND field_id = ".$this->getDB()->quote($exit_udf_field_id, "integer")
						   ." WHERE active = 1 "
						   );
		while($row = $this->getDB()->fetchAssoc($res)) {
			echo $row['value'];
			if(preg_match('/^(19|20)\d\d-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])$/', $row['value'])) {
				$ret[] = $row["usr_id"];
			}
		}
		return $ret;
	}

	public function getUserObject($usr_id) {
		return new ilObjUser($usr_id);
	}

	public function getBookedOrWaitingCourseFor($usr_id) {
		$usr_utils = gevUserUtils::getInstance($usr_id);
		return $usr_utils->getBookedAndWaitingCourses();
	}

	public function cancelBookingsFor($crs_id, $usr_id) {
		// I know, this is not timezone safe.
		$now = @date("Y-m-d");

		$crs_utils = gevCourseUtils::getInstance($crs_id);
		$start_date = $crs_utils->getStartDate();
		if ($start_date === null) {
			return self::CRS_NO_START_DATE;
		}

		if ($start_date->get(IL_CAL_DATE) >= $now) {
			$crs_utils->getBookings()->cancelWithoutCosts($usr_id);
			return self::CRS_CANCELD;
		}
		else {
			return self::CRS_START_DATE_EXPIRED;
		}
	}

	public function sendExitMail($crs_id, $usr_id) {
		$mails = new gevCrsAutoMails($crs_id);
		$mails->send("participant_left_corporation", array($usr_id));
	}

	public function getUserOrgUnits($usr_id) {
		$orgu_tree = ilObjOrgUnitTree::_getInstance();
		return $orgu_tree->getOrgUnitOfUser($usr_id, 0, true);
	}

	public function deassignOrgUnit($usr_id, $orgu_id) {
		$orgu_utils = gevOrgUnitUtils::getInstance($orgu_id);
		$orgu_utils->deassignUser($usr_id, "Mitarbeiter");
		$orgu_utils->deassignUser($usr_id, "Vorgesetzter");
	}

	public function assignToExitOrgunit($usr_id) {
		$exit_orgu_ref_id = $this->settings->getOrgUnitExited();
		$exit_orgu_obj_id = gevObjectUtils::getObjId($exit_orgu_ref_id);
		$exit_orgu_utils = gevOrgUnitUtils::getInstance($exit_orgu_obj_id);

		$exit_orgu_utils->assignUser($usr_id, "Mitarbeiter");
	}

	protected function getDB() {
		return $this->db;
	}
}