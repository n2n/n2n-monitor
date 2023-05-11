<?php
namespace n2n\monitor\ui;

use n2n\impl\web\ui\view\html\HtmlView;

/**
 * MonitorHtmlBuilderMeta provides non-html meta information to your views. You can access it over
 */
class MonitorHtmlBuilder {
	public function __construct(HtmlView $view) {
		$this->view = $view;
	}

	public function setup() {
		$htmlMeta = $this->view->getHtmlBuilder()->meta();
		$htmlMeta->addMeta(['name' => 'monitor-url', 'content' => $this->view->getN2nContext()->getMonitor()->getAlertPostUrl()]);
		$htmlMeta->addJs('monitor.min.js', 'n2n\monitor', true, true, ['defer', 'type' => 'module']);
	}
}