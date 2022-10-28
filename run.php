<?php
require 'vendor/autoload.php';

$spider = new \Adolphgithub\Cnregion\Spider();

$spider->run(__DIR__ . DIRECTORY_SEPARATOR . 'data');