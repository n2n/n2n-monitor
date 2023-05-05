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
use n2n\util\io\IoUtils;
use n2n\util\ex\IllegalStateException;

class MonitorModel {
	private const NS = 'n2n\\monitor';
	private const CACHE_STORE_NAME_ALERT = 'alert';
	private const ALERT_URL_KEY_FILE_NAME = 'key';

	private CacheStore $monitorCacheStore;

	private VarStore $varStore;

	public function __construct(private N2nContext $n2nContext) {

	}

	public function isCorrectKey(string $key): bool {
		return $key === $this->getMonitorUrlKey(false);
	}

	public function getMonitorUrlKey(bool $create): ?string {
		$fsPath = $this->getAlertUrlKeyFsPath($create);

		$key = null;
		if ($fsPath->exists()) {
			$key = IoUtils::getContents($fsPath);
		}

		if (!empty($key)) {
			return $key;
		} else if (!$create) {
			return null;
		}

		$key = HashUtils::base36Md5Hash(IllegalStateException::try(fn() => random_bytes(4)));
		IoUtils::putContents($fsPath, $key);
		return $key;
	}

	public function removeMonitorUrlKey(): void {
		$this->getAlertUrlKeyFsPath(null, 'key', false)->delete();
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
		if (empty($this->getAlertCacheItems($severity))) {
			return;
		}

		$alertException = new AlertException(AlertException::class . ':' . $severity?->value,
				$this->createAlertMessage($severity), 0);
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
		$message .= ' occurred.';
		return $message;
	}

	public function createAlertsMailText(AlertSeverity $severity = null): string {
		$reportText = '';
		foreach ($this->getAlertCacheItems($severity) as $alertCacheItem) {
			$reportText .= 'Alert occurred ' . $alertCacheItem->occurrences . ' times' . PHP_EOL;
			$reportText .= $alertCacheItem->text . PHP_EOL;
			$reportText .= '-----------------------------------------------' . PHP_EOL;
		}
		return $reportText;
	}

	private function getCacheStore(): CacheStore {
		return $this->monitorCacheStore
				?? $this->monitorCacheStore = $this->n2nContext->getAppCache()->lookupCacheStore(self::NS);
	}

	private function getAlertUrlKeyFsPath(bool $createFile = true): FsPath {
		return $this->getVarStore()->requestFileFsPath(VarStore::CATEGORY_TMP, self::NS, null,
				self::ALERT_URL_KEY_FILE_NAME, true, $createFile, false, true);
	}

	private function getVarStore(): VarStore {
		return $this->varStore ?? $this->varStore = $this->n2nContext->getVarStore();
	}

	private function storeAlertCacheItem(AlertCacheItem $alertCacheItem): void {
		$this->getCacheStore()->store(self::CACHE_STORE_NAME_ALERT,
				['key' => $alertCacheItem->key, 'severity' => $alertCacheItem->severity->value], $alertCacheItem);
	}
}