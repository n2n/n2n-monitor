<?php

namespace n2n\monitor\controller;

use n2n\web\http\controller\ControllerAdapter;
use n2n\monitor\model\MonitorModel;
use n2n\web\http\PageNotFoundException;
use n2n\util\StringUtils;
use n2n\context\attribute\Inject;
use n2n\web\http\BadRequestException;
use n2n\util\EnumUtils;
use n2n\core\ext\AlertSeverity;

class MonitorController extends ControllerAdapter {

	#[Inject]
	private MonitorModel $monitorModel;

	/**
	 *
	 *
	 * @param string $key
	 * @return void
	 * @throws \n2n\util\JsonEncodeFailedException
	 * @throws \n2n\util\io\fs\FileOperationException
	 */
	public function index(string $key) {
		if (!$this->monitorModel->isMonitoringEnabled()) {
			throw new PageNotFoundException();
		}

		$this->checkKey($key);
		$this->checkAlertsOverload();

		try {
			$requestBody = StringUtils::jsonDecode($this->getHttpContext()->getRequest()->getBody(), true);
		} catch (\JsonException $e) {
			throw new BadRequestException();
		}

		$alertHash = md5($requestBody['hash']);
		unset($requestBody['hash']);
		$encodedContentJson = StringUtils::jsonEncode($requestBody);
		$severity = EnumUtils::valueToUnit($requestBody['severity'] ?? null, AlertSeverity::class);

		$this->getN2nContext()->getMonitor()->alert(self::class, $alertHash, $encodedContentJson, $severity);

		$this->sendJson($encodedContentJson);
	}

	private function checkKey(string $key) {
		if (!$this->monitorModel->isCorrectKey($key)) {
			throw new PageNotFoundException();
		}
	}

	/**
	 * Checks if too many Alert files were created recently.
	 * Configurable in app.ini
	 *
	 * @return void
	 * @throws \n2n\util\io\fs\FileOperationException
	 */
	private function checkAlertsOverload(): void {
		$currentTime = new \DateTime();
		$fsPathsCreatedAnHourAgo = array_filter($this->monitorModel->getAlertFsPaths(),
				fn($fsPath) => $fsPath->getLastMod() <= $currentTime->sub(new \DateInterval('PT1H')));

		//@todo: make configurable in app ini
		if (500 < count($fsPathsCreatedAnHourAgo)) {
			throw new PageNotFoundException();
		}
	}
}