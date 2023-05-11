<?php
namespace n2n\monitor\ui;

use n2n\impl\web\ui\view\html\HtmlView;

/**
 * MonitorHtmlBuilderMeta provides non-html meta information to your views. You can access it over
 */
class MonitorHtmlBuilder {
	public function __construct(HtmlView $view) {
		$this->view = $view;
		$this->meta = new MonitorHtmlBuilderMeta($view);
	}

	public function meta(): MonitorHtmlBuilderMeta {
		return $this->meta;
	}
}