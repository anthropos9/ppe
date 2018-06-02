<?php
require_once 'vendor/autoload.php';

use Ppe\Export;

if (empty($argv[1])) {
    print 'No arguments provided' . PHP_EOL;
    exit;
}

$export = new Export();
$task   = $argv[1];

switch ($task) {
    case 'reformat':
        $dir    = $argv[2] ?? 'recipes';
        $format = $argv[3] ?? 'txt';

        $export->$task($dir, $format);
        break;
}
