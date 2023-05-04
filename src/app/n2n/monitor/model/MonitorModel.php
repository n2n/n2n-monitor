<?php
namespace n2n\monitor\model;

use n2n\util\cache\CacheStore;
use n2n\mail\Mail;
use n2n\mail\Transport;
use n2n\util\HashUtils;
use n2n\monitor\bo\AlertCacheItem;
use n2n\util\cache\CacheItem;
use n2n\core\container\N2nContext;

class MonitorModel {
	private const NS = 'n2nmonitor';
	private const CACHE_STORE_NAME_ALERT = 'alert';

	private CacheStore $monitorCacheStore;

	public function __construct(private N2nContext $n2nContext) {

	}

	public function isCorrectKey(string $key): bool {
		return $key === $this->getMonitorUrlKey(false);
	}

	public function getMonitorUrlKey(bool $create): ?string {
		$alertUrlKeyCacheItem = $this->getCacheStore()->get('monitorUrlKey', []);
		if ($alertUrlKeyCacheItem !== null) {
			return $alertUrlKeyCacheItem->getData();
		}

		if (!$create) {
			return null;
		}

		$hash = HashUtils::base36Md5Hash(random_bytes(4));
		$this->getCacheStore()->store('monitorUrlKey', [], $hash);
		return $hash;
	}

	public function getAlertCacheItem(string $key): ?AlertCacheItem {
		return $this->getCacheStore()->get(self::CACHE_STORE_NAME_ALERT, ['key' => $key])?->getData();
	}

	/**
	 * @return AlertCacheItem[]
	 */
	public function getAlertCacheItems(): array {
		return array_map(fn(CacheItem $cacheItem) => $cacheItem->getData(), $this->getCacheStore()->findAll(self::CACHE_STORE_NAME_ALERT));
	}

	public function cacheAlert(AlertCacheItem $alertCacheItem): void {
		$existingAlertCacheItem = $this->getAlertCacheItem($alertCacheItem->key);
		if ($existingAlertCacheItem !== null) {
			$alertCacheItem->occurrences = $existingAlertCacheItem->occurrences + 1;
		}

		$this->storeAlertCacheItem($alertCacheItem);
	}

	public function sendAlertsReportMail(): void {
		if (count($this->getAlertCacheItems()) === 0) {
			return;
		}

		$appConfig = $this->n2nContext->getAppConfig();
		$defaultAddresser = $appConfig->mail()->getDefaultAddresser();
		$logMailRecipient = $appConfig->error()->getLogMailRecipient();
		$mail = new Mail($defaultAddresser, 'Alerts Report', $this->createAlertsReportText(), $logMailRecipient);
		Transport::send($mail);
	}

	public function clearCache(): void {
		$this->getCacheStore()->clear();
	}

	public function createAlertsReportText(): string {
		$reportText = '';
		foreach ($this->getAlertCacheItems() as $alertCacheItem) {
			$reportText .= 'Alert occured ' . $alertCacheItem->occurrences . ' times' . PHP_EOL;
			$reportText .= 'Severity: ' . $alertCacheItem->severity->value . PHP_EOL;
			$reportText .= $alertCacheItem->text . PHP_EOL;
			$reportText .= '-----------------------------------------------' . PHP_EOL;
		}
		return $reportText;
	}

	private function getCacheStore(): CacheStore {
		return $this->monitorCacheStore
				?? $this->monitorCacheStore = $this->n2nContext->getAppCache()->lookupCacheStore(self::NS);
	}

	private function storeAlertCacheItem(AlertCacheItem $alertCacheItem): void {
		$this->getCacheStore()->store(self::CACHE_STORE_NAME_ALERT, ['key' => $alertCacheItem->key], $alertCacheItem);
	}
}