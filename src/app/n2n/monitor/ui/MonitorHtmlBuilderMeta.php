<?php
namespace n2n\monitor\ui;

use \n2n\impl\web\ui\view\html\HtmlView;

class MonitorHtmlBuilderMeta {
	public function __construct(private HtmlView $view) {
	}

	public function setup() {
		$htmlMeta = $this->view->getHtmlBuilder()->meta();
		$htmlMeta->addMeta(['name' => 'monitor-url', 'content' => $this->view->getN2nContext()->getMonitor()->getAlertPostUrl()]);
		$htmlMeta->addJs('monitor.min.js', 'n2n\monitor', true, true, ['defer', 'type' => 'module']);
	}
}