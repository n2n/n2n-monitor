<?php
namespace n2n\monitor\batch;

use n2n\context\attribute\Lookupable;
use n2n\monitor\model\MonitorModel;
use n2n\context\attribute\Inject;

#[Lookupable]
class MonitorBatchJob {

	#[Inject]
	private MonitorModel $monitorModel;

	public function _onNewHour() {
		$this->monitorModel->sendAlertsReportMail();
		$this->monitorModel->clearCache();
	}
}