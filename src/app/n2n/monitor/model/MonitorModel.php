<?php
namespace n2n\monitor\model;

use n2n\cache\CacheStore;
use n2n\util\HashUtils;
use n2n\monitor\bo\AlertCacheItem;
use n2n\cache\CacheItem;
use n2n\core\VarStore;
use n2n\core\N2N;
use n2n\core\ext\AlertSeverity;
use n2n\monitor\alert\AlertException;
use n2n\util\io\fs\FsPath;
use n2n\util\io\IoUtils;
use n2n\util\ex\IllegalStateException;

class MonitorModel {
	public const NS = 'n2n\\monitor';
	private const CACHE_STORE_NAME_ALERT = 'alert';
	private const ALERT_URL_KEY_FILE_NAME = 'key';

	public function __construct(private VarStore $varStore, private CacheStore $monitorCacheStore) {

	}

	/**
	 * Compares the passed key to the key stored in the file system.
	 * @param string $key
	 * @return bool
	 */
	public function isCorrectKey(string $key): bool {
		return $key === $this->getMonitorUrlKey(false);
	}

	/**
	 * Returns the key stored in the file system.
	 * If the key does not exist and $create is true, a new key will be created.
	 *
	 * @param bool $create
	 * @return string|null
	 * @throws \n2n\util\io\IoException
	 */
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

		$key = IllegalStateException::try(fn() => HashUtils::base36Md5Hash(random_bytes(4)));
		IoUtils::putContents($fsPath, $key);
		return $key;
	}

	/**
	 * Deletes the MonitorUrlKey
	 * @return void
	 */
	public function removeMonitorUrlKey(): void {
		$this->getAlertUrlKeyFsPath(false)->delete();
	}

	/**
	 * Returns the corresponding AlertCacheItem for the passed key and severity.
	 * If no AlertCacheItem is found, null will be returned.
	 *
	 * @param string $key
	 * @param AlertSeverity $severity
	 * @return AlertCacheItem|null
	 */
	public function getAlertCacheItem(string $key, AlertSeverity $severity): ?AlertCacheItem {
		return $this->monitorCacheStore->get(self::CACHE_STORE_NAME_ALERT,
				['key' => $key, 'severity' => $severity->value])?->getData();
	}

	/**
	 * Gets all AlertCacheItems corresponding to severity.
	 * If null is passed, all AlertCacheItems will be returned.
	 *
	 * @return AlertCacheItem[]
	 */
	public function getAlertCacheItems(?AlertSeverity $severity = null): array {
		$characteristicNeedles = [];
		if ($severity !== null) {
			$characteristicNeedles['severity'] = $severity->value;
		}

		return array_map(fn(CacheItem $cacheItem) => $cacheItem->getData(),
				$this->monitorCacheStore->findAll(self::CACHE_STORE_NAME_ALERT, $characteristicNeedles));
	}

	/**
	 * Stores the passed AlertCacheItem by using {@see self::storeAlertCacheItem()}.
	 *
	 * @param AlertCacheItem $alertCacheItem
	 * @return void
	 */
	public function cacheAlert(AlertCacheItem $alertCacheItem): void {
		$existingAlertCacheItem = $this->getAlertCacheItem($alertCacheItem->key, $alertCacheItem->severity);
		if ($existingAlertCacheItem !== null) {
			$alertCacheItem->occurrences = $existingAlertCacheItem->occurrences + 1;
		}

		$this->storeAlertCacheItem($alertCacheItem);
	}

	/**
	 * Sends a mail with the alerts by using {@see self::createAlertMessage()} and {@see self::createAlertsReportText()}.
	 *
	 * @param AlertSeverity|null $severity
	 * @return void
	 */
	public function sendAlertsReportMail(?AlertSeverity $severity = null): void {
		if (empty($this->getAlertCacheItems($severity))) {
			return;
		}

		$alertException = new AlertException(AlertException::class . ':' . $severity?->value,
				$this->createAlertMessage($severity), 0);
		$alertException->setLogMessage($this->createAlertsReportText($severity));
		N2N::getExceptionHandler()->log($alertException);
	}

	/**
	 * Clears the {@see CacheStore} provided in {@see self::__construct()}.
	 * @return void
	 */
	public function clearCache(?AlertSeverity $severity = null): void {
		if ($severity === null) {
			$this->monitorCacheStore->clear();
			return;
		}

		$this->monitorCacheStore->removeAll(self::CACHE_STORE_NAME_ALERT,
				['severity' => $severity->value]);
	}

	/**
	 * Writes a message containing the alertCount, severity and message.
	 *
	 * @param AlertSeverity|null $severity
	 * @return string
	 */
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

	/**
	 * Creates a Report text listing all alerts that match the passed severity.
	 *
	 * @param AlertSeverity|null $severity
	 * @return string
	 */
	public function createAlertsReportText(?AlertSeverity $severity = null): string {
		$reportText = '';
		foreach ($this->getAlertCacheItems($severity) as $alertCacheItem) {
			$reportText .= 'Alert occurred ' . $alertCacheItem->occurrences . ' times' . PHP_EOL;
			$reportText .= $alertCacheItem->text . PHP_EOL;
			$reportText .= '-----------------------------------------------' . PHP_EOL;
		}
		return $reportText;
	}

	/**
	 * Requests the {@see FsPath} for the {@see self::ALERT_URL_KEY_FILE_NAME} containing the UrlKey for storing alerts.
	 * If createFile is set to true, the file will be created if it doesnt exist yet.
	 *
	 * @param bool $createFile
	 * @return FsPath
	 */
	private function getAlertUrlKeyFsPath(bool $createFile = true): FsPath {
		return $this->varStore->requestFileFsPath(VarStore::CATEGORY_TMP, self::NS, null,
				self::ALERT_URL_KEY_FILE_NAME, true, $createFile, false, true);
	}



	/**
	 * Stores the given AlertCacheItem in the {@see CacheStore} provided in {@see self::__construct()}.
	 * The key of the AlertCacheItem is used as the key for the CacheItem.
	 * The severity of the AlertCacheItem is used as a characteristic for the CacheItem.
	 *
	 * @param AlertCacheItem $alertCacheItem
	 * @return void
	 */
	private function storeAlertCacheItem(AlertCacheItem $alertCacheItem): void {
		$this->monitorCacheStore->store(self::CACHE_STORE_NAME_ALERT,
				['key' => $alertCacheItem->key, 'severity' => $alertCacheItem->severity->value], $alertCacheItem);
	}
}