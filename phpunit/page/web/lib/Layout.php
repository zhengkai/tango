<?php
class Layout extends Tango\Page\Layout {

	protected $_sLayout = '';

	protected function _runBase() {

		require Page::getBaseDir() . '/tpl/head.php';

		echo "\n";

		require Page::getBaseDir() . '/tpl/nav.php';

		echo "\n";

		echo '<div class="container">', "\n";

		echo $this->_sBody;

		echo "\n", '</div>', "\n\n";

		require Page::getBaseDir() . '/tpl/foot.php';

		return TRUE;
	}

	protected function _runBaseMeta() {

		require Page::getBaseDir() . '/tpl/head_meta.php';

		echo "\n";

		echo '<div class="container">', "\n";

		echo $this->_sBody;

		echo "\n", '</div>', "\n\n";

		require Page::getBaseDir() . '/tpl/foot_time.php';

		return TRUE;
	}
}
