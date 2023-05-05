<?php
namespace n2n\monitor\batch;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use n2n\core\container\N2nContext;
use n2n\monitor\model\MonitorModel;
use ReflectionClass;
use n2n\core\ext\AlertSeverity;

class MonitorBatchJobTest extends TestCase {
	private MonitorBatchJob $monitorBatchJob;
	private MockObject $monitorModelMock;

	protected function setUp(): void {
		$this->monitorModelMock = $this->createMock(MonitorModel::class);
		$this->monitorBatchJob = new MonitorBatchJob();

		$reflection = new ReflectionClass($this->monitorBatchJob);
		$property = $reflection->getProperty('monitorModel');
		$property->setAccessible(true);
		$property->setValue($this->monitorBatchJob, $this->monitorModelMock);
	}

	public function testOnNewHour(): void {
		$this->monitorModelMock->expects($this->once())
				->method('sendAlertsReportMail')
				->with($this->equalTo(AlertSeverity::HIGH));

		$this->monitorBatchJob->_onNewHour();
	}

	public function testOnNewDay(): void {
		$this->monitorModelMock->expects($this->once())
				->method('sendAlertsReportMail')
				->with($this->equalTo(AlertSeverity::LOW));

		$this->monitorBatchJob->_onNewDay();
	}
}