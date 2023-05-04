<?php
namespace n2n\monitor\controller;

use n2n\web\http\controller\ControllerAdapter;
use n2n\web\http\PageNotFoundException;
use n2n\util\StringUtils;
use n2n\web\http\BadRequestException;
use n2n\util\EnumUtils;
use n2n\core\ext\AlertSeverity;
use n2n\monitor\model\MonitorModel;
use n2n\core\container\N2nContext;

class MonitorController extends ControllerAdapter {

	private MonitorModel $monitorModel;

	private function _init(N2nContext $n2nContext) {
		$this->monitorModel = new MonitorModel($n2nContext);
	}

	/**
	 *
	 *
	 * @param string $key
	 * @return void
	 * @throws \n2n\util\JsonEncodeFailedException
	 * @throws \n2n\util\io\fs\FileOperationException
	 */
	public function index(string $key) {
		$this->checkKey($key);
		$this->checkAlertsOverload();

		try {
			$requestBody = StringUtils::jsonDecode($this->getHttpContext()->getRequest()->getBody(), true);
		} catch (\JsonException $e) {
			throw new BadRequestException('Invalid JSON provided', 0, $e);
		}
		var_dump($requestBody);
		$discriminator = $requestBody['discriminator'];
		unset($requestBody['discriminator']);

		$encodedContentJson = StringUtils::jsonEncode($requestBody);
		$severity = EnumUtils::valueToUnit($requestBody['severity'] ?? null, AlertSeverity::class);

		$this->getN2nContext()->getMonitor()->alert(self::class, $discriminator, $encodedContentJson, $severity);
	}

	private function checkKey(string $key) {
		if (!$this->monitorModel->isCorrectKey($key)) {
			throw new PageNotFoundException();
		}
	}

	/**
	 * Checks if too many Alerts are cached.
	 * Configurable in app.ini
	 *
	 * @return void
	 * @throws PageNotFoundException
	 */
	private function checkAlertsOverload(): void {
		/**
		 * @todo: make configurable in app.ini
		 */
		if (500 < count($this->monitorModel->getAlertCacheItems())) {
			throw new PageNotFoundException();
		}
	}
}