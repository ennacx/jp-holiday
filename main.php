<?php
declare(strict_types=1);

require 'vendor/autoload.php';

# ホスティング先のURL
const HOST_URL = 'https://github.com/ennacx/jp-holiday';

try{
    (new JpHoliday\JpHoliday())->generate();

    // スケジュール実行用のGit更新対象ファイルに実行日時を上書き
    file_put_contents('.exec_timestamp', date('Y-m-d\TH:i:sO'));
} catch(Exception $e){
    echo $e->getMessage();
}