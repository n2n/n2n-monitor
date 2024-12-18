<?php
/*
 * Copyright (c) 2012-2016, Hofmänner New Media.
 * DO NOT ALTER OR REMOVE COPYRIGHT NOTICES OR THIS FILE HEADER.
 *
 * This file is part of the N2N FRAMEWORK.
 *
 * The N2N FRAMEWORK is free software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * N2N is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details: http://www.gnu.org/licenses/
 *
 * The following people participated in this project:
 *
 * Andreas von Burg.....: Architect, Lead Developer
 * Bert Hofmänner.......: Idea, Frontend UI, Community Leader, Marketing
 * Thomas Günther.......: Developer, Hangar
 */
namespace n2n\monitor\alert;

use n2n\core\ext\N2nMonitor;
use n2n\core\container\impl\AddOnContext;
use n2n\util\magic\impl\SimpleMagicContext;
use n2n\core\ext\AlertSeverity;
use n2n\monitor\model\MonitorModel;
use n2n\monitor\bo\AlertCacheItem;
use n2n\util\uri\Url;
use n2n\core\container\N2nContext;

class AlertToExceptionN2nMonitor extends SimpleMagicContext implements N2nMonitor, AddOnContext {
	private MonitorModel $monitorModel;
	private N2nContext $n2nContext;

	public function __construct(array $objs, N2nContext $n2nContext) {
		parent::__construct($objs);
		$this->n2nContext = $n2nContext;
	}

	function getMonitorModel(): MonitorModel {
		return $this->monitorModel
				?? $this->monitorModel = new MonitorModel($this->n2nContext->getVarStore(),
						$this->n2nContext->getAppCache()->lookupCacheStore(MonitorModel::NS, false));
	}

	function alert(string $namespace, string $discriminator, string $text, AlertSeverity $severity = AlertSeverity::MEDIUM): void {
		$alertCacheItem = new AlertCacheItem(md5($namespace . $discriminator), $text, $severity);
		$this->getMonitorModel()->cacheAlert($alertCacheItem);
	}

	function getAlertPostUrl(): ?Url {
		if (!$this->n2nContext->isHttpContextAvailable()) {
			return null;
		}

		$request = $this->n2nContext->getHttpContext()->getRequest();
		return $request->getHostUrl()->ext($request->getContextPath()->ext('_monitoring',
				$this->getMonitorModel()->getMonitorUrlKey(true)));
	}

	function finalize(): void {
	}

	function hasMagicObject(string $id): bool {
		return false;
	}

	function lookupMagicObject(string $id, bool $required = true, ?string $contextNamespace = null): mixed {
		return null;
	}
}