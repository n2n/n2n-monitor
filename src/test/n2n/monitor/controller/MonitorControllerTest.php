<?php
namespace n2n\monitor\controller;

use PHPUnit\Framework\TestCase;
use n2n\test\TestEnv;
use n2n\monitor\model\MonitorModel;
use n2n\core\ext\AlertSeverity;
use n2n\util\StringUtils;
use n2n\core\container\N2nContext;
use n2n\web\http\BadRequestException;

class MonitorControllerTest extends TestCase {
	private MonitorModel $monitorModel;

	function setUp() : void {
		$this->reset();
		$n2nContext = TestEnv::lookup(N2nContext::class);
		$this->monitorModel = new MonitorModel($n2nContext->getVarStore(),
				$n2nContext->getAppCache()->lookupCacheStore(MonitorModel::NS, false));
		$this->monitorModel->clearCache();
	}

	/**
	 * Tests MonitorController->index() with a valid key and a valid example alert.
	 * @return void
	 */
	function testIndex() {
		$key = $this->monitorModel->getMonitorUrlKey(true);

		$dataArr = [
			'discriminator' => 'TypeErrorhttp://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.412719011',
			'severity' => 'high',
			'name' => 'TypeError',
			'message' => 'something',
			'stackTrace' => 'TypeError: something\n    at EventAddComponent.start (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.41:27190:11)\n    at EventAddComponent_click_HostBindingHandler (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.41:27223:20)\n    at executeListenerWithErrorHandling (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:74356:12)\n    at wrapListenerIn_markDirtyAndPreventDefault (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:74387:18)\n    at HTMLButtonElement.<anonymous> (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:97071:34)\n    at _ZoneDelegate.invokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/polyfills.js?v=1.41:382:171)\n    at http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84089:49\n    at AsyncStackTaggingZoneSpec.onInvokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84089:30)\n    at _ZoneDelegate.invokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/polyfills.js?v=1.41:382:54)\n    at Object.onInvokeTask (http://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/vendor.js?v=1.41:84391:25)'
		];

		$response = TestEnv::http()->newRequest()
				->post(['_monitoring', $key])
				->bodyJson($dataArr)->exec();

		$this->assertEquals(200, $response->getStatus());
		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertEquals(AlertSeverity::HIGH, $this->monitorModel->getAlertCacheItems()[0]->severity);
		$this->assertEquals(1, $this->monitorModel->getAlertCacheItems()[0]->occurrences);

		$alertCacheItemData = StringUtils::jsonDecode($this->monitorModel->getAlertCacheItems()[0]->text, true);
		$this->assertEquals($dataArr['name'], $alertCacheItemData['name']);
		$this->assertEquals($dataArr['message'], $alertCacheItemData['message']);
		$this->assertEquals($dataArr['stackTrace'], $alertCacheItemData['stackTrace']);

		$dataArr['message'] = 'something else';
		$dataArr['stackTrace'] = 'something else';
		$dataArr['name'] = 'something else';
		$dataArr['severity'] = 'high';

		$response = TestEnv::http()->newRequest()
				->post(['_monitoring', $key])
				->bodyJson($dataArr)->exec();

		$alertCacheItems = $this->monitorModel->getAlertCacheItems();
		$alertCacheItem = array_shift($alertCacheItems);

		$this->assertEquals(200, $response->getStatus());
		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertEquals(AlertSeverity::HIGH, $alertCacheItem->severity);
		$this->assertEquals(2, $alertCacheItem->occurrences);

		$alertCacheItemData = StringUtils::jsonDecode($alertCacheItem->text, true);
		$this->assertEquals($dataArr['name'], $alertCacheItemData['name']);
		$this->assertEquals($dataArr['message'], $alertCacheItemData['message']);
		$this->assertEquals($dataArr['stackTrace'], $alertCacheItemData['stackTrace']);
	}

	/**
	 * Test if BadRequestException is thrown if invalid severity is passed
	 * @return void
	 */
	function testIndexWithWrongSeverity() {
		$this->expectException(BadRequestException::class);

		$key = $this->monitorModel->getMonitorUrlKey(true);

		$dataArr = [
				'discriminator' => 'TypeErrorhttp://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.412719011',
				'severity' => 'asdf',
		];

		TestEnv::http()->newRequest()
				->post(['_monitoring', $key])
				->bodyJson($dataArr)->exec();
	}

	/**
	 * Test that alert without any data except discriminator can be cached
	 * @return void
	 */
	function testIndexWithNoSeverityPassed() {
		$key = $this->monitorModel->getMonitorUrlKey(true);

		$dataArr = [
				'discriminator' => 'TypeErrorhttp://app.localhost/event-manager/src-php/public/assets/em/emapp-dev/main.js?v=1.412719011',
		];

		TestEnv::http()->newRequest()
				->post(['_monitoring', $key])
				->putLookupInjection(MonitorModel::class, $this->monitorModel)
				->bodyJson($dataArr)->exec();

		$request = TestEnv::http()->newRequest()
				->post(['_monitoring', $key])
				->putLookupInjection(MonitorModel::class, $this->monitorModel)
				->bodyJson($dataArr)->exec();

		$this->assertEquals(200, $request->getStatus());
		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertEquals(AlertSeverity::MEDIUM, $this->monitorModel->getAlertCacheItems()[0]->severity);
		$this->assertEquals(2, $this->monitorModel->getAlertCacheItems()[0]->occurrences);
	}

	private function reset() {
		if (TestEnv::container()->tm()->hasOpenTransaction()) {
			TestEnv::container()->tm()->getRootTransaction()->rollBack();
		}

		TestEnv::getN2nContext()->clearLookupInjections();
		TestEnv::replaceN2nContext();
	}
}