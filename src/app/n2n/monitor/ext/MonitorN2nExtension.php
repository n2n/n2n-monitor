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
namespace n2n\monitor\ext;

use n2n\monitor\alert\AlertToExceptionN2nMonitor;
use n2n\core\ext\ConfigN2nExtension;
use n2n\core\N2nApplication;
use n2n\core\container\impl\AppN2nContext;

class MonitorN2nExtension implements ConfigN2nExtension {

	function __construct(private N2nApplication $n2nApplication) {
	}

	function applyToN2nContext(AppN2nContext $appN2nContext): void {
		if (!$this->n2nApplication->getAppConfig()->error()->isMonitorEnabled()) {
			return;
		}

		$monitor = new AlertToExceptionN2nMonitor([], $appN2nContext);
		$appN2nContext->setMonitor($monitor);
		$appN2nContext->addAddonContext($monitor);
	}
}