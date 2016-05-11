<?php
HTML::addCSS('/res/fixed.css');

require Page::getBaseDir() . '/tpl/head.php';

echo "\n\n";

require Page::getBaseDir() . '/tpl/nav.php';

echo "\n\n";

require Page::getBaseDir() . '/tpl/title.php';

echo "\n\n";

echo '<div class="container">', "\n";

echo $this->_sBody;

require Page::getBaseDir() . '/tpl/foot.php';

?>
</div>

</body>
</html>
