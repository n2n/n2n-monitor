<?php
namespace n2n\monitor\bo;

use n2n\core\ext\AlertSeverity;
use n2n\util\DateUtils;

class AlertCacheItem implements \JsonSerializable {
	public int $occurrences = 1;
	public \DateTimeImmutable $dateTime;

	public function __construct(public string $key, public string $text, public AlertSeverity $severity) {
		$this->dateTime = new \DateTimeImmutable();
	}

	public function jsonSerialize(): mixed {
		return [
			'occurrences' => $this->occurrences,
			'dateTime' => DateUtils::dateTimeToSql(\DateTime::createFromImmutable($this->dateTime)),
			'text' => $this->text,
			'severity' => $this->severity
		];
	}
}