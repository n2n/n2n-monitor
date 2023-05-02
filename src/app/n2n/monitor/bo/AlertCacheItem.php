<?php
namespace n2n\monitor\bo;

use n2n\core\ext\AlertSeverity;

class AlertCacheItem implements \JsonSerializable {
	public int $occurrences = 1;

	public function __construct(public string $key, public string $text, public AlertSeverity $severity) {
	}

	public function jsonSerialize(): mixed {
		return [
			'occurrences' => $this->occurrences,
			'text' => $this->text,
			'severity' => $this->severity
		];
	}
}