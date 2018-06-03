<?php
require_once 'vendor/autoload.php';

use Ppe\Export;

$export = new Export();

if (empty($argv[1])) {
    $export->message('No arguments provided');
    exit;
}

$task = $argv[1];

switch ($task) {
    case 'import':

        if (empty($argv[2]) || empty($argv[3])) {
            $export->message('Missing username or password');
            exit;
        }

        $un = $argv[2];
        $pw = $argv[3];

        $export->login($un, $pw);
        $list = $export->getRecipes();
        $export->download($list);
        break;
}
