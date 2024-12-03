<?php

namespace n2n\monitor\alert;

use n2n\util\ex\LogInfo;
use Throwable;

class AlertException extends \RuntimeException implements LogInfo {

	function __construct(private ?string $hashCode = null, ?string $message = null, ?int $code = null,
			?Throwable $previous = null) {
		parent::__construct((string) $message, (int) $code, $previous);
	}

	private ?string $logMessage = null;

	function hashCode(): ?string {
		return $this->hashCode;
	}

	function setLogMessage(?string $logMessage) {
		$this->logMessage = $logMessage;
	}

	function getLogMessage(): ?string {
		return $this->logMessage;
	}
}