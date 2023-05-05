<?php
namespace n2n\monitor\model;

use n2n\util\cache\CacheStore;
use n2n\util\HashUtils;
use n2n\monitor\bo\AlertCacheItem;
use n2n\util\cache\CacheItem;
use n2n\core\container\N2nContext;
use n2n\core\VarStore;
use n2n\io\managed\impl\FsFileSource;
use n2n\core\N2N;
use n2n\core\ext\AlertSeverity;
use n2n\monitor\alert\AlertException;
use n2n\util\io\fs\FsPath;

class MonitorModel {
	private const NS = 'n2nmonitor';
	private const CACHE_STORE_NAME_ALERT = 'alert';

	private CacheStore $monitorCacheStore;

	private VarStore $varStore;

	public function __construct(private N2nContext $n2nContext) {

	}

	public function isCorrectKey(string $key) {
		return $key === $this->getMonitorUrlKey(false);
	}

	public function getMonitorUrlKey(bool $create): ?string {
		$fsPath = $this->getMonitorFsPath(null, 'key', $create);
		$fileResource = new FsFileSource($fsPath);

		if (!$fsPath->exists() && !$create) {
			return null;
		}

		if ($fsPath->isEmpty() && $create) {
			$key = HashUtils::base36Md5Hash(random_bytes(4));
			$fileResource->createOutputStream()->write($key);
			return $key;
		}

		return $fileResource->createInputStream()->read();
	}

	public function removeMonitorUrlKey(): void {
		$this->getMonitorFsPath(null, 'key', false)->delete();
	}

	public function getAlertCacheItem(string $key, AlertSeverity $severity): ?AlertCacheItem {
		return $this->getCacheStore()->get(self::CACHE_STORE_NAME_ALERT,
				['key' => $key, 'severity' => $severity->value])?->getData();
	}

	/**
	 * @return AlertCacheItem[]
	 */
	public function getAlertCacheItems(AlertSeverity $severity = null): array {
		$characteristicNeedles = [];
		if ($severity !== null) {
			$characteristicNeedles['severity'] = $severity->value;
		}

		return array_map(fn(CacheItem $cacheItem) => $cacheItem->getData(),
				$this->getCacheStore()->findAll(self::CACHE_STORE_NAME_ALERT, $characteristicNeedles));
	}

	public function cacheAlert(AlertCacheItem $alertCacheItem): void {
		$existingAlertCacheItem = $this->getAlertCacheItem($alertCacheItem->key, $alertCacheItem->severity);
		if ($existingAlertCacheItem !== null) {
			$alertCacheItem->occurrences = $existingAlertCacheItem->occurrences + 1;
		}

		$this->storeAlertCacheItem($alertCacheItem);
	}

	public function sendAlertsReportMail(AlertSeverity $severity = null): void {
		if (count($this->getAlertCacheItems($severity)) === 0) {
			return;
		}

		$alertException = new AlertException(md5(uniqid()), $this->createAlertMessage($severity), 0);
		$alertException->setLogMessage($this->createAlertsMailText($severity));
		N2N::getExceptionHandler()->log($alertException);
	}

	public function clearCache(): void {
		$this->getCacheStore()->clear();
	}

	private function createAlertMessage(?AlertSeverity $severity): string {
		$alertCount = array_sum(array_map(
				fn($aci) => $aci->occurrences, $this->getAlertCacheItems($severity)));
		$message = $alertCount . ' Alerts';
		if ($severity) {
			$message .= ' with severity ' . $severity->value;
		}
		$message .= ' occured.';
		return $message;
	}

	public function createAlertsMailText(AlertSeverity $severity = null): string {
		$reportText = '';
		foreach ($this->getAlertCacheItems($severity) as $alertCacheItem) {
			$reportText .= 'Alert occured ' . $alertCacheItem->occurrences . ' times' . PHP_EOL;
			$reportText .= 'Severity: ' . $alertCacheItem->severity->value . PHP_EOL;
			$reportText .= $alertCacheItem->text . PHP_EOL;
			$reportText .= '-----------------------------------------------' . PHP_EOL;
		}
		return $reportText;
	}

	private function getCacheStore() {
		return $this->monitorCacheStore
				?? $this->monitorCacheStore = $this->n2nContext->getAppCache()->lookupCacheStore('n2nmonitor');
	}

	private function getMonitorFsPath(?string $dirName, string $fileName, bool $createFile = true): FsPath {
		return $this->getVarStore()->requestFileFsPath(VarStore::CATEGORY_TMP, self::NS, $dirName, $fileName,
				true, $createFile, false);
	}

	private function getVarStore(): VarStore {
		return $this->varStore ?? $this->varStore = $this->n2nContext->getVarStore();
	}

	private function storeAlertCacheItem(AlertCacheItem $alertCacheItem): void {
		$this->getCacheStore()->store(self::CACHE_STORE_NAME_ALERT,
				['key' => $alertCacheItem->key, 'severity' => $alertCacheItem->severity->value], $alertCacheItem);
	}
}