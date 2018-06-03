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

    case 'import':
        if (empty($argv[2]) || empty($argv[3])) {
            print 'Missing username or password';
            exit;
        }
        $un = $argv[2];
        $pw = $argv[3];

        $export->login($un,$pw);
        $list = $export->getRecipes();
        $export->download($list);
        break;
}
