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

    /** @var string[] 祝日枠から除外する祭日名 */
    private const array EXCLUDE_HOLIDAY_NAMES = ["銀行休業日", "クリスマス", "大晦日"];

    private DateTime $dateObj;
    /** @var int 昨年 */
    private int $prevYear;
    /** @var int 本年 */
    private int $currentYear;
    /** @var int 翌年 */
    private int $nextYear;

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
        $this->prevYear    = intval((clone $this->dateObj)->modify('-1 year')->format('Y'));
        $this->currentYear = intval($this->dateObj->format('Y'));
        $this->nextYear    = intval((clone $this->dateObj)->modify('+1 year')->format('Y'));

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
    public function getHoliday(): void {

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

        if($result === false){
            throw new Exception("Could not read calendar");
        }

        if($result !== ''){
            $this->raw = str_replace("\r", '', $result);
        }
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
            }
        }
    }

    /**
     * 前年・本年・翌年のみにフィルタリング
     *
     * @param array $v
     * @return array
     */
    private function filter3Years(array $v): array {

        return array_merge(
            $v[$this->prevYear]    ?? [],
            $v[$this->currentYear] ?? [],
            $v[$this->nextYear]    ?? [],
        );
    }

    /**
     * ファイル化
     *
     * @return void
     */
    private function putFile(): void {

        $func = function(array $arr, string $k, int $sortFlag): array {
            $res = array_column($arr, 'summary', $k);
            $this->sort($res, $sortFlag);

            return $res;
        };

        // 3年間
        {
            // 祝日
            $dateShu3Years = $func($this->filter3Years($this->shukujitsu), 'date', SORT_NATURAL);
            $tsShu3Years   = $func($this->filter3Years($this->shukujitsu), 'timestamp', SORT_NUMERIC);
            $this->putJson('/shu/date.json', $dateShu3Years);
            $this->putJson('/shu/ts.json', $tsShu3Years);
            $this->putCsv('/shu/date.csv', $dateShu3Years);
            $this->putCsv('/shu/ts.csv', $tsShu3Years);

            // 祭日
            $dateSai3Years = $func($this->filter3Years($this->saijitsu), 'date', SORT_NATURAL);
            $tsSai3Years   = $func($this->filter3Years($this->saijitsu), 'timestamp', SORT_NUMERIC);
            $this->putJson('/sai/date.json', $dateSai3Years);
            $this->putJson('/sai/ts.json', $tsSai3Years);
            $this->putCsv('/sai/date.csv', $dateSai3Years);
            $this->putCsv('/sai/ts.csv', $tsSai3Years);

            // 祝祭日
            $temp = array_merge($dateShu3Years, $dateSai3Years);
            ksort($temp, SORT_NATURAL);
            $this->putJson('/date.json', $temp);
            $this->putCsv('/date.csv', $temp);
            $temp = $tsSai3Years + $tsShu3Years;
            ksort($temp, SORT_NUMERIC);
            $this->putJson('/ts.json', $temp);
            $this->putCsv('/ts.csv', $temp);
            unset($temp);
        }

        // 年ごとに
        {
            $saveFunc = function(int $year, array $arr, ?string $s = null) use(&$func, &$toJson){

                $temp1 = $func($arr, 'date', SORT_NATURAL);
                $temp2 = $func($arr, 'timestamp', SORT_NATURAL);

                $path = "/{$year}";
                if($s !== null)
                    $path .= "/{$s}";

                $this->putJson("{$path}/date.json", $temp1);
                $this->putCsv("{$path}/date.csv", $temp1);
                $this->putJson("{$path}/ts.json", $temp2);
                $this->putCsv("{$path}/ts.csv", $temp2);
            };

            $years = array_unique(array_merge(
                array_keys($this->shukujitsu),
                array_keys($this->saijitsu)
            ));
            sort($years, SORT_NUMERIC);

            foreach($years as $year){
                $shuArr = $this->shukujitsu[$year] ?? [];
                $saiArr = $this->saijitsu[$year] ?? [];
                $merge = array_merge($shuArr, $saiArr);

                if(!empty($merge))
                    $saveFunc($year, $merge);
                unset($merge);

                if(!empty($shuArr))
                    $saveFunc($year, $shuArr, 'shu');
                unset($shuArr);

                if(!empty($saiArr))
                    $saveFunc($year, $saiArr, 'sai');
                unset($saiArr);
            }
        }
    }

    /**
     * ソート
     *
     * @param  array $v
     * @param  int   $sortFlag
     * @return void
     */
    private function sort(array &$v, int $sortFlag): void {
        ksort($v, $sortFlag);
    }

    /**
     * JSON化
     *
     * @param  string $path
     * @param  array  $arr
     * @return void
     */
    private function putJson(string $path, array $arr): void {

        $fullPath = $this->saveBasePath.$path;

        Functions::makeDirectory(dirname($fullPath));

        file_put_contents($fullPath, json_encode($arr, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * CSV化
     *
     * @param  string $path
     * @param  array  $arr
     * @return void
     */
    private function putCsv(string $path, array $arr): void {

        $fullPath = $this->saveBasePath.$path;

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