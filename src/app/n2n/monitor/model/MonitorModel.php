<?php
namespace n2n\monitor\model;

use n2n\core\VarStore;
use n2n\io\managed\impl\FsFileSource;
use n2n\core\container\N2nContext;
use n2n\context\attribute\RequestScoped;
use n2n\context\attribute\Inject;
use n2n\util\cache\CacheStore;
use n2n\mail\Mail;
use n2n\mail\Transport;
use n2n\core\N2N;
use n2n\util\HashUtils;
use n2n\monitor\bo\AlertCacheItem;
use n2n\bind\build\impl\Bind;
use n2n\util\cache\CacheItem;

#[RequestScoped]
class MonitorModel {

	private const NS = 'n2nmonitor';

	#[Inject]
	private N2nContext $n2nContext;

	private CacheStore $monitorCacheStore;

	public function isCorrectKey(string $key) {
		return $key === $this->geMonitorUrlKey(false);
	}

	public function geMonitorUrlKey(bool $create): string {
		$fsPath = $this->getMonitorFsPath(null, 'key', $create);

		$fileResource = new FsFileSource($fsPath);
		if ($fsPath->isEmpty() && $create) {
			$fileResource->createOutputStream()->write(HashUtils::base36Md5Hash(random_bytes(4)));
		}

		return $fileResource->createInputStream()->read();
	}

	public function getAlertCacheItem(string $key): ?AlertCacheItem {
		return $this->getCacheStore()->get('alert', ['key' => $key])?->getData();
	}

	/**
	 * @return AlertCacheItem[]
	 */
	public function getAlertCacheItems(): array {
		return array_map(fn(CacheItem $cacheItem) => $cacheItem->getData(), $this->getCacheStore()->findAll('alert'));
	}

	public function cacheAlert(AlertCacheItem $existingAlertCacheItem): AlertCacheItem {
		$existingAlertCacheItem = $this->getAlertCacheItem($existingAlertCacheItem->key);
		if ($existingAlertCacheItem !== null) {
			$existingAlertCacheItem->occurrences = $existingAlertCacheItem->occurrences + 1;
		}

		$this->storeAlertCacheItem($existingAlertCacheItem);

		return $this->getAlertCacheItem($existingAlertCacheItem->key);
	}

	/**
	 * @return bool
	 */
	public function isMonitoringEnabled(): bool {
		return true; //@todo: get config and read flag
	}

	/**
	 * @return \n2n\util\io\fs\FsPath[]
	 * @throws \n2n\util\io\fs\FileOperationException
	 */
	public function getAlertFsPaths(): array {
		return $this->getVarStore()->requestDirFsPath(VarStore::CATEGORY_TMP, self::NS, 'error')->getChildren();
	}

	public function sendAlertsReportMail() {
		$defaultAddresser = N2N::getAppConfig()->mail()->getDefaultAddresser();
		$logMailRecipient = N2N::getAppConfig()->error()->getLogMailRecipient();
		$mail = new Mail($defaultAddresser, 'Alerts Report', $this->createAlertsReportText(), $logMailRecipient);
		Transport::send($mail);
	}

	public function clearCache() {
		$this->getCacheStore()->clear();
	}

	private function createAlertsReportText() {
		$reportText = '';
		foreach ($this->getAlertCacheItems() as $alertCacheItem) {
			$reportText .= 'Error occured ' . $alertCacheItem->occurrences . ' times' . PHP_EOL;
			$reportText .= $alertCacheItem->text . PHP_EOL;
			$reportText .= '-----------------------------------------------' . PHP_EOL;
		}
		return $reportText;
	}

	private function getCacheStore() {
		return $this->monitorCacheStore ?? $this->monitorCacheStore = $this->n2nContext->getAppCache()->lookupCacheStore('n2nmonitor');
	}

	private function getMonitorFsPath(?string $dirName, string $fileName, bool $createFile = true) {
		return $this->getVarStore()->requestFileFsPath(VarStore::CATEGORY_TMP, self::NS, $dirName, $fileName,
				true, $createFile, false);
	}

	private function getVarStore() {
		return $this->varStore ?? $this->varStore = $this->n2nContext->getVarStore();
	}

	private function storeAlertCacheItem(AlertCacheItem $alertCacheItem) {
		$this->getCacheStore()->store('alert', ['key' => $alertCacheItem->key],$alertCacheItem);
	}
}