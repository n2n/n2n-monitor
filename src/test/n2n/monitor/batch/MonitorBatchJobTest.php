<?php
namespace n2n\monitor\batch;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use n2n\monitor\model\MonitorModel;
use ReflectionClass;
use n2n\core\ext\AlertSeverity;
use n2n\monitor\bo\AlertCacheItem;

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

		$this->monitorModelMock->method('getAlertCacheItems')->with(AlertSeverity::HIGH)->willReturn([
				new AlertCacheItem('test1', 'test', AlertSeverity::HIGH),
				new AlertCacheItem('test2', 'test', AlertSeverity::HIGH),
		]);

		$this->monitorModelMock->method('getAlertCacheItems')->with(AlertSeverity::LOW)->willReturn([
				new AlertCacheItem('test1', 'test', AlertSeverity::LOW),
				new AlertCacheItem('test2', 'test', AlertSeverity::LOW),
		]);
	}

	public function testOnNewHour(): void {
		$this->monitorModelMock->expects($this->once())
				->method('sendAlertsReportMail')
				->with($this->equalTo(AlertSeverity::HIGH));
		$this->monitorModelMock->expects($this->once())
				->method('clearCache')
				->with($this->equalTo(AlertSeverity::HIGH));


		$this->monitorBatchJob->_onNewHour();
	}

	public function testOnNewDay(): void {
		$this->monitorModelMock->expects($this->once())
				->method('sendAlertsReportMail')
				->with($this->equalTo(AlertSeverity::LOW));
		$this->monitorModelMock->expects($this->once())
				->method('clearCache')
				->with($this->equalTo(AlertSeverity::LOW));

		$this->monitorBatchJob->_onNewDay();
	}
}