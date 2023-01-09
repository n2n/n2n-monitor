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
use n2n\core\N2N;
use n2n\core\container\impl\AddOnContext;
use n2n\core\container\impl\AppN2nContext;
use n2n\util\magic\impl\SimpleMagicContext;

class AlertToExceptionN2nMonitor extends SimpleMagicContext implements N2nMonitor, AddOnContext {

	function alert(string $namespace, string $hash, string $text): void {
		$alertException = new AlertException(md5($namespace . ':' . $hash));
		$alertException->setLogMessage($text);
		N2N::getExceptionHandler()->log($alertException);
	}

	function copyTo(AppN2nContext $appN2NContext): void {
		$appN2NContext->setMonitor($this);
		$appN2NContext->addAddonContext($this);
	}

	function finalize(): void {
	}

}