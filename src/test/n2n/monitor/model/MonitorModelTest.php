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
		$this->monitorModel = new MonitorModel($n2nContext->getVarStore(),
				$n2nContext->getAppCache()->lookupCacheStore(MonitorModel::NS));

		$this->monitorModel->clearCache();
		$this->monitorModel->removeMonitorUrlKey();
	}

	public function testGetMonitorUrlKey() {
		$this->assertNull($this->monitorModel->getMonitorUrlKey(false));
		$this->assertNotNull($key = $this->monitorModel->getMonitorUrlKey(true));
		$this->assertEquals($key, $this->monitorModel->getMonitorUrlKey(false));
	}

	public function testGetAlertCacheItem() {
		$storedAlertCacheItem = new AlertCacheItem('test', 'test', AlertSeverity::LOW);
		$this->monitorModel->cacheAlert($storedAlertCacheItem);

		$fetchedAlertCacheItem = $this->monitorModel->getAlertCacheItem('test', AlertSeverity::LOW);
		$this->assertEquals($storedAlertCacheItem->text, $fetchedAlertCacheItem->text);
		$this->assertEquals($storedAlertCacheItem->occurrences, $fetchedAlertCacheItem->occurrences);
		$this->assertEquals($storedAlertCacheItem->severity, $fetchedAlertCacheItem->severity);
	}

	public function testGetAlertCacheItems() {
		$cacheItem1 = new AlertCacheItem('test1', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test2', 'test', AlertSeverity::HIGH);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(2, $this->monitorModel->getAlertCacheItems());
	}

	public function testGetAlertCacheItemsOfOneSeverity() {
		$cacheItem1 = new AlertCacheItem('test1', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test2', 'test', AlertSeverity::HIGH);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(1, $this->monitorModel->getAlertCacheItems(AlertSeverity::LOW));
		$this->assertCount(1, $this->monitorModel->getAlertCacheItems(AlertSeverity::HIGH));
	}

	public function testCacheAlert() {
		$cacheItem1 = new AlertCacheItem('test', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test', 'test', AlertSeverity::LOW);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertEquals(2, $this->monitorModel->getAlertCacheItem('test', AlertSeverity::LOW)->occurrences);
	}

	public function testRemoveAlertsWithSpecificSeverity() {
		$cacheItem1 = new AlertCacheItem('test1', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test2', 'test', AlertSeverity::HIGH);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->assertCount(2, $this->monitorModel->getAlertCacheItems());

		$this->monitorModel->clearCache(AlertSeverity::LOW);

		$this->assertCount(1, $this->monitorModel->getAlertCacheItems());
		$this->assertNull($this->monitorModel->getAlertCacheItem('test1', AlertSeverity::LOW));
	}

	public function testIsEmptyAfterClearCache() {
		$cacheItem1 = new AlertCacheItem('test1', 'test', AlertSeverity::LOW);
		$cacheItem2 = new AlertCacheItem('test2', 'test', AlertSeverity::HIGH);

		$this->monitorModel->cacheAlert($cacheItem1);
		$this->monitorModel->cacheAlert($cacheItem2);

		$this->monitorModel->clearCache();

		$this->assertEmpty($this->monitorModel->getAlertCacheItems());
	}

	public function testClearCacheAndRemoveMonitorUrlKey() {
		$urlKey = $this->monitorModel->getMonitorUrlKey(true);
		$this->monitorModel->cacheAlert(new AlertCacheItem('test', 'test', AlertSeverity::LOW));

		$this->assertNotEmpty( $this->monitorModel->getAlertCacheItems());

		$this->monitorModel->clearCache();

		$this->assertEmpty( $this->monitorModel->getAlertCacheItems());

		$this->monitorModel->removeMonitorUrlKey();

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