<?php
Page::setLayout('BaseMeta');

echo '<p>', $T['foo'], '</p>';

HTML::addJS('/bootstrap/3.3.6/js/bootstrap.min.js');
HTML::addCSS('/bootstrap/3.3.6/css/bootstrap.min.css');
