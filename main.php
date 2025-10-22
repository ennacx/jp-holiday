<?php
declare(strict_types=1);

require 'vendor/autoload.php';

# ホスティング先のURL
const HOST_URL = 'https://github.com/ennacx/jp-holiday';

try{
    $JpHoliday = new JpHoliday\JpHoliday();

    $JpHoliday->generate();
} catch(Exception $e){
    echo $e->getMessage();
}