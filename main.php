<?php
declare(strict_types=1);

require 'vendor/autoload.php';

try{
    (new JpHoliday\JpHoliday())->generate();

    // スケジュール実行用のGit更新対象ファイルに実行日時を上書き
    file_put_contents('.exec_timestamp', date('Y-m-d H:i:s'));
} catch(Exception $e){
    echo $e->getMessage();
}