<?php
declare(strict_types=1);

namespace JpHoliday;

use DateTime;
use Exception;
use JpHoliday\Utils\Functions;

/**
 * Googleカレンダーから祝祭日を取得
 */
class JpHoliday {

    /** @var string Googleカレンダーの祝祭日ICSを取得するURL */
    private const string HOLIDAY_BASE_URL = 'https://calendar.google.com/calendar/ical/%s/public/full.ics';

    /** @var string Googleカレンダーの祝祭日ICSを取得するID */
    private const string HOLIDAY_ID = 'ja.japanese#holiday@group.v.calendar.google.com';

    /** @var int 取得前後年 */
    private const int FILTER_YEARS = 3;

    /** @var string[] 祝日枠から除外する祭日名 */
    private const array EXCLUDE_HOLIDAY_NAMES = ["銀行休業日", "クリスマス", "大晦日"];

    /** @var string 祝日格納ディレクトリ名 */
    private const string SHUKUJITSU_DIR_NAME = 'shu';
    /** @var string 祭日格納ディレクトリ名 */
    private const string SAIJITSU_DIR_NAME = 'sai';

    /** @var string 日付形式のベースファイル名 */
    private const string DATE_FILE_NAME = 'date';
    /** @var string 日付形式のベースファイル名 */
    private const string TIMESTAMP_FILE_NAME = 'ts';

    private DateTime $dateObj;
    /** @var int 本年 */
    private int $currentYear;

    /** @var string メージャーバージョン */
    private string $majorVersion;

    /** @var string 結果ファイル保存の基本パス */
    private string $saveBasePath;

    /** @var string GoogleカレンダーのICS取得実URL */
    private string $gCalUrl;
    /** @var string|null Rawデータ */
    private ?string $raw = null;

    /** @var array 祝日 */
    private array $shukujitsu = [];
    /** @var array 祭日 */
    private array $saijitsu = [];

    /**
     * コンストラクター
     */
    public function __construct(){

        date_default_timezone_set('Asia/Tokyo');

        $this->dateObj     = new DateTime();
        $this->currentYear = intval($this->dateObj->format('Y'));

        $this->majorVersion = '';
        $temp = file_get_contents(dirname(__DIR__).'/version.txt');
        if($temp !== false){
            $this->majorVersion = explode('.', $temp)[0];
        }
        unset($temp);

        $this->saveBasePath = sprintf('%s/dist/%s', dirname(__DIR__), $this->majorVersion);
        Functions::makeDirectory($this->saveBasePath);

        $this->gCalUrl = sprintf(self::HOLIDAY_BASE_URL, urlencode(self::HOLIDAY_ID));
    }

    /**
     * 祝祭日ファイル生成
     *
     * @return void
     * @throws Exception
     */
    public function getHoliday(){

        // データを取得して
        $this->getFromGCal();

        // 扱いやすく整理して
        $this->summarizeRaw();

        // ファイル化する
        $this->putFile();
    }

    /**
     * GoogleカレンダーからICS取得
     *
     * @return void
     * @throws Exception
     */
    private function getFromGCal(): void {

        $result = file_get_contents($this->gCalUrl);

        if($result === false)
            throw new Exception('Could not read calendar');

        if($result !== '')
            $this->raw = str_replace("\r", '', $result);
    }

    /**
     * ICS生データを整理する
     *
     * @return void
     */
    private function summarizeRaw(): void {

        if(!empty($this->raw)){
            foreach(Functions::getGenerator(explode('END:VEVENT', $this->raw)) as $holidayRaw){
                // 日付取得
                if(empty(preg_match('/DTSTART;VALUE=DATE:(?<date>\d{8})/m', $holidayRaw, $matches)))
                    continue;
                $dateObj = DateTime::createFromFormat('Ymd', $matches['date']);
                if($dateObj === false)
                    continue;
                unset($matches);

                // 名称取得
                if(empty(preg_match('/SUMMARY:(?<summary>.+)/m', $holidayRaw, $matches)) || !isset($matches['summary']))
                    continue;
                $summary = $matches['summary'];
                unset($matches);

                // 祝祭日判定
                $isShukujitsu = true;
                if(!empty(preg_match('/DESCRIPTION:(?<desc>.+)/m', $holidayRaw, $matches)) && (str_contains($matches['desc'], "祭日") || in_array($summary, self::EXCLUDE_HOLIDAY_NAMES, true)))
                    $isShukujitsu = false;
                unset($matches);

                $intYear = intval($dateObj->format('Y'));

                $data = [
                    'date'      => $dateObj->format('Y-m-d'),
                    'timestamp' => $dateObj->format('U'),
                    'summary'   => $summary
                ];

                if($isShukujitsu)
                    $this->shukujitsu[$intYear][] = $data;
                else
                    $this->saijitsu[$intYear][] = $data;

                unset($data);
            }
        }
    }

    /**
     * 本年を中心とした前後年にフィルタリング
     *
     * @param  array $v
     * @return array
     */
    private function filterYears(array $v): array {

        // 返却用
        $ret = [];

        // 取得間隔
        $durationYear = self::FILTER_YEARS;
        if(self::FILTER_YEARS <= 2 || self::FILTER_YEARS > 10)
            $durationYear = 2;
        else if(self::FILTER_YEARS % 2 === 1)
            $durationYear = self::FILTER_YEARS - 1;

        // 按分
        $div = intval($durationYear / 2);

        for($i = $this->currentYear - $div; $i <= $this->currentYear + $div; $i++){
            if(!array_key_exists($i, $v))
                continue;

            $ret = array_merge($ret, $v[$i]);
        }

        return $ret;
    }

    /**
     * ファイル化
     *
     * @return void
     */
    private function putFile(): void {

        /**
         * 配列ソートを行うサブファンクション
         *
         * @param  array $v
         * @param  int   $sortFlag
         * @return void
         */
        $sortFunc = function(array &$v, int $sortFlag): void {
            ksort($v, $sortFlag);
        };

        /**
         * 指定キーの値をキーとした、祝祭日名の配列を生成するサブファンクション
         *
         * @param  array  $arr
         * @param  string $k
         * @param  int    $sortFlag
         * @return array
         */
        $extractFunc = function(array $arr, string $k, int $sortFlag) use(&$sortFunc): array {

            $ret = array_column($arr, 'summary', $k);
            $sortFunc($ret, $sortFlag);

            return $ret;
        };

        /**
         * 保存先フルパス生成サブファンクション
         *
         * @param  string|null $dir
         * @param  string      $fileName
         * @param  string|null $extension
         * @return string
         */
        $pathGenerateFunc = function(?string $dir, string $fileName, ?string $extension = null): string {
            return sprintf(
                '%s/%s%s',
                ($dir !== null) ? sprintf('/%s', $dir) : '',
                $fileName,
                ($extension !== null) ? sprintf('.%s', $extension) : ''
            );
        };

        // n年間
        {
            // 祝日
            $dateShuYears = $extractFunc($this->filterYears($this->shukujitsu), 'date', SORT_NATURAL);
            $tsShuYears   = $extractFunc($this->filterYears($this->shukujitsu), 'timestamp', SORT_NUMERIC);
            $this->putJson($pathGenerateFunc(self::SHUKUJITSU_DIR_NAME, self::DATE_FILE_NAME), $dateShuYears);
            $this->putJson($pathGenerateFunc(self::SHUKUJITSU_DIR_NAME, self::TIMESTAMP_FILE_NAME), $tsShuYears);
            $this->putCsv($pathGenerateFunc(self::SHUKUJITSU_DIR_NAME, self::DATE_FILE_NAME), $dateShuYears);
            $this->putCsv($pathGenerateFunc(self::SHUKUJITSU_DIR_NAME, self::TIMESTAMP_FILE_NAME), $tsShuYears);

            // 祭日
            $dateSaiYears = $extractFunc($this->filterYears($this->saijitsu), 'date', SORT_NATURAL);
            $tsSaiYears   = $extractFunc($this->filterYears($this->saijitsu), 'timestamp', SORT_NUMERIC);
            $this->putJson($pathGenerateFunc(self::SAIJITSU_DIR_NAME, self::DATE_FILE_NAME), $dateSaiYears);
            $this->putJson($pathGenerateFunc(self::SAIJITSU_DIR_NAME, self::TIMESTAMP_FILE_NAME), $tsSaiYears);
            $this->putCsv($pathGenerateFunc(self::SAIJITSU_DIR_NAME, self::DATE_FILE_NAME), $dateSaiYears);
            $this->putCsv($pathGenerateFunc(self::SAIJITSU_DIR_NAME, self::TIMESTAMP_FILE_NAME), $tsSaiYears);

            // 祝祭日
            $temp = array_merge($dateSaiYears, $dateShuYears); // FIXME: 祝日優先 (後方上書)
            ksort($temp, SORT_NATURAL);
            $this->putJson($pathGenerateFunc(null, self::DATE_FILE_NAME), $temp);
            $this->putCsv($pathGenerateFunc(null, self::DATE_FILE_NAME), $temp);
            unset($temp);
            $temp = $tsShuYears + $tsSaiYears; // FIXME: 祝日優先 (前方上書)
            ksort($temp, SORT_NUMERIC);
            $this->putJson($pathGenerateFunc(null, self::TIMESTAMP_FILE_NAME), $temp);
            $this->putCsv($pathGenerateFunc(null, self::TIMESTAMP_FILE_NAME), $temp);
            unset($temp);
        }

        // 年ごとに
        {
            /**
             * 年ごとのファイルを生成するサブファンクション
             *
             * @param  int         $year
             * @param  array       $arr
             * @param  string|null $s
             * @return void
             */
            $saveFunc = function(int $year, array $arr, ?string $s = null) use(&$extractFunc, &$pathGenerateFunc){

                $temp1 = $extractFunc($arr, 'date', SORT_NATURAL);
                $temp2 = $extractFunc($arr, 'timestamp', SORT_NATURAL);

                $path = strval($year);
                if($s !== null)
                    $path .= "/{$s}";

                $this->putJson($pathGenerateFunc($path, self::DATE_FILE_NAME), $temp1);
                $this->putCsv($pathGenerateFunc($path, self::DATE_FILE_NAME), $temp1);
                $this->putJson($pathGenerateFunc($path, self::TIMESTAMP_FILE_NAME), $temp2);
                $this->putCsv($pathGenerateFunc($path, self::TIMESTAMP_FILE_NAME), $temp2);
            };

            $years = array_unique(array_merge(
                array_keys($this->shukujitsu),
                array_keys($this->saijitsu)
            ));
            sort($years, SORT_NUMERIC);

            foreach($years as $year){
                $shuArr = $this->shukujitsu[$year] ?? [];
                $saiArr = $this->saijitsu[$year] ?? [];
                $merge = array_merge($saiArr, $shuArr); // FIXME: 祝日優先 (後方上書)

                if(!empty($merge))
                    $saveFunc($year, $merge);
                unset($merge);

                if(!empty($shuArr))
                    $saveFunc($year, $shuArr, self::SHUKUJITSU_DIR_NAME);
                unset($shuArr);

                if(!empty($saiArr))
                    $saveFunc($year, $saiArr, self::SAIJITSU_DIR_NAME);
                unset($saiArr);
            }
        }
    }

    /**
     * JSON化
     *
     * @param  string $path
     * @param  array  $arr
     * @param  string $extension
     * @return void
     */
    private function putJson(string $path, array $arr, string $extension = 'json'): void {

        $fullPath = $this->saveBasePath . $path .'.'. $extension;

        Functions::makeDirectory(dirname($fullPath));

        file_put_contents($fullPath, json_encode($arr, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * CSV化
     *
     * @param  string $path
     * @param  array  $arr
     * @param  string $extension
     * @return void
     */
    private function putCsv(string $path, array $arr, string $extension = 'csv'): void {

        $fullPath = $this->saveBasePath . $path .'.'. $extension;

        Functions::makeDirectory(dirname($fullPath));

        try{
            $fp = fopen($fullPath, 'w');

            foreach(Functions::getGenerator($arr) as $k => $v){
                fwrite($fp, sprintf("%s\n", implode(',', array_map(fn($v): string => "\"{$v}\"", [$k, $v]))));
            }
        } finally{
            fclose($fp);
        }
    }
}