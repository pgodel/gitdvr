#!/usr/bin/env php
<?php

include 'vendor/autoload.php';

use PAG\GitDvr\Command\ReplayCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ReplayCommand());
$application->run();