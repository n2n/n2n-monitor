<?php
namespace n2n\monitor\batch;

use n2n\context\attribute\Lookupable;
use n2n\monitor\model\MonitorModel;
use n2n\core\container\N2nContext;
use n2n\core\ext\AlertSeverity;

#[Lookupable]
class MonitorBatchJob {
	private MonitorModel $monitorModel;

	private function _init(N2nContext $n2nContext): void {
		$this->monitorModel = new MonitorModel($n2nContext->getVarStore(),
				$n2nContext->getAppCache()->lookupCacheStore(MonitorModel::NS));
	}

	public function _onNewHour(): void {
		$this->monitorModel->sendAlertsReportMail(AlertSeverity::HIGH);
		$this->monitorModel->clearCache(AlertSeverity::HIGH);
	}

	public function _onNewDay(): void {
		$this->monitorModel->sendAlertsReportMail(AlertSeverity::LOW);
		$this->monitorModel->clearCache(AlertSeverity::LOW);
	}
}