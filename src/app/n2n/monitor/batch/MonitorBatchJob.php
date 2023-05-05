<?php
namespace n2n\monitor\batch;

use n2n\context\attribute\Lookupable;
use n2n\monitor\model\MonitorModel;
use n2n\context\attribute\Inject;
use n2n\core\container\N2nContext;
use n2n\core\ext\AlertSeverity;

#[Lookupable]
class MonitorBatchJob {
	private MonitorModel $monitorModel;

	private function _init(N2nContext $n2nContext) {
		$this->monitorModel = new MonitorModel($n2nContext);
	}

	public function _onNewHour() {
		$this->monitorModel->sendAlertsReportMail(AlertSeverity::HIGH);
	}

	public function _onNewDay() {
		$this->monitorModel->sendAlertsReportMail(AlertSeverity::LOW);
	}
}