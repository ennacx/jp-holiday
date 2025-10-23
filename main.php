<?php
declare(strict_types=1);

require 'vendor/autoload.php';

# ホスティング先のURL
const HOST_URL = 'https://github.com/ennacx/jp-holiday';

try{
    // カレンダー生成
    $httpStatus = (new JpHoliday\JpHoliday())->generate();

    // 結果出力
    echo json_encode(['status' => $httpStatus], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch(Exception $e){
    echo $e->getMessage();
}