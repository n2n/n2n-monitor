<?php
namespace n2n\monitor\model;

use PHPUnit\Framework\TestCase;
use n2n\test\TestEnv;
use n2n\monitor\bo\AlertCacheItem;
use n2n\core\ext\AlertSeverity;
use n2n\core\container\N2nContext;

class MonitorModelTest extends TestCase {

	private MonitorModel $monitorModel;

	function setUp() : void {
		$this->reset();
		$n2nContext = TestEnv::lookup(N2nContext::class);
		$this->monitorModel = new MonitorModel($n2nContext);

		if (isset($this->monitorModel)) {
			$this->monitorModel->clearCache();
		}
	}

	public function testGetMonitorUrlKey() {
		$this->assertNull($this->monitorModel->getMonitorUrlKey(false));
		$this->assertNotNull($key = $this->monitorModel->getMonitorUrlKey(true));
		$this->assertEquals($key, $this->monitorModel->getMonitorUrlKey(false));
	}

	public function testGetAlertCacheItem() {
		$storedAlertCacheItem = new AlertCacheItem('test', 'test', AlertSeverity::LOW);
		$this->monitorModel->cacheAlert($storedAlertCacheItem);

		$fetchedAlertCacheItem = $this->monitorModel->getAlertCacheItem('test');
		$this->assertEquals($storedAlertCacheItem->text, $fetchedAlertCacheItem->text);
		$this->assertEquals($storedAlertCacheItem->occurrences, $fetchedAlertCacheItem->occurrences);
		$this->assertEquals($storedAlertCacheItem->severity, $fetchedAlertCacheItem->severity);
	}

	public function testGetAlertCacheItems() {
		$cacheItem1 = new AlertCacheItem('test1', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test2', 'test', AlertSeverity::LOW);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(2, $this->monitorModel->getAlertCacheItems());
	}

	public function testCacheAlert() {
		$cacheItem1 = new AlertCacheItem('test', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test', 'test', AlertSeverity::LOW);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertEquals(2, $this->monitorModel->getAlertCacheItem('test')->occurrences);
	}

//	public function testSendAlertsReportMail() {
//		$dataArr = [
//				'severity' => 'high',
//				'name' => 'TypeError',
//				'message' => 'something',
//				'stackTrace' => 'TypeError: something\n    at EventAddComponent.start (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.41:27190:11)\n    at EventAddComponent_click_HostBindingHandler (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.41:27223:20)\n    at executeListenerWithErrorHandling (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:74356:12)\n    at wrapListenerIn_markDirtyAndPreventDefault (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:74387:18)\n    at HTMLButtonElement.<anonymous> (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:97071:34)\n    at _ZoneDelegate.invokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/polyfills.js?v=1.41:382:171)\n    at http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84089:49\n    at AsyncStackTaggingZoneSpec.onInvokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84089:30)\n    at _ZoneDelegate.invokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/polyfills.js?v=1.41:382:54)\n    at Object.onInvokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84391:25)'
//		];
//
//		$this->monitorModel->cacheAlert(new AlertCacheItem('test1', StringUtils::jsonEncode($dataArr), AlertSeverity::LOW));
//		$this->monitorModel->cacheAlert(new AlertCacheItem('test1', StringUtils::jsonEncode($dataArr), AlertSeverity::HIGH));
//		$this->monitorModel->cacheAlert(new AlertCacheItem('test2', StringUtils::jsonEncode($dataArr), AlertSeverity::HIGH));
//
//		var_dump($this->monitorModel->createAlertsReportText());
//	}

	public function testClearCache() {
		$urlKey = $this->monitorModel->getMonitorUrlKey(true);
		$this->monitorModel->cacheAlert(new AlertCacheItem('test', 'test', AlertSeverity::LOW));

		$this->monitorModel->clearCache();

		$this->assertEmpty( $this->monitorModel->getAlertCacheItems());
		$this->assertNotEquals($urlKey, $this->monitorModel->getMonitorUrlKey(true));
	}

	private function reset() {
		if (TestEnv::container()->tm()->hasOpenTransaction()) {
			TestEnv::container()->tm()->getRootTransaction()->rollBack();
		}

		TestEnv::getN2nContext()->clearLookupInjections();
		TestEnv::replaceN2nContext();
	}
}