<?php

// TODO enable/disable using GUI
require 'src/addons/debug/plugins/xhprof/vendor/xhprof/external/header.php';

$REX = array();
$REX['REDAXO'] = true;
$REX['HTDOCS_PATH'] = '../';
$REX['BACKEND_FOLDER'] = 'redaxo';

require 'src/core/master.inc.php';

require rex_path::core('index_be.inc.php');

// TODO check why the shutdown func is not run to include the footer
require 'src/addons/debug/plugins/xhprof/vendor/xhprof/external/footer.php';
