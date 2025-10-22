<?php
declare(strict_types=1);

require 'vendor/autoload.php';

# ホスティング先のURL
const HOST_URL = 'https://github.com/ennacx/jp-holiday';

try{
    $JpHoliday = new JpHoliday\JpHoliday();

    // カレンダーに変更があれば生成
    $JpHoliday->generate();
    // 生成時にはタイムスタンプをファイルに
    $JpHoliday->putTimestamp();
} catch(Exception $e){
    echo $e->getMessage();
}