<?php
declare(strict_types=1);

namespace JpHoliday;

use DateTime;
use Exception;
use JpHoliday\Enums\CalFormat;
use JpHoliday\Enums\CalType;
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

    /** @var string 生成ファイル保存ディレクトリ名 */
    private const string DISTRIBUTE_DIR_NAME = 'dist';

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

        if(!defined('DS')){
            define('DS', DIRECTORY_SEPARATOR);
        }

        date_default_timezone_set('Asia/Tokyo');

        $this->dateObj     = new DateTime();
        $this->currentYear = intval($this->dateObj->format('Y'));

        $this->majorVersion = '';
        $temp = file_get_contents(dirname(__DIR__) . DS .'version.txt');
        if($temp !== false){
            $this->majorVersion = explode('.', $temp)[0];
        }
        unset($temp);

        $this->saveBasePath = dirname(__DIR__) . DS . self::DISTRIBUTE_DIR_NAME . DS . $this->majorVersion;
        Functions::makeDirectory($this->saveBasePath);

        $this->gCalUrl = sprintf(self::HOLIDAY_BASE_URL, urlencode(self::HOLIDAY_ID));
    }

    /**
     * 祝祭日ファイル生成
     *
     * @return void
     * @throws Exception
     */
    public function generate(): void {

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

        // FIXME: [MEMO] php.ini側で allow_url_fopen の値が 0(OFF) だとWarningになる
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

                // 時刻のリセット
                $dateObj->setTime(0, 0, 0);

                // 最終的に格納するキーと配列
                $intYear = intval($dateObj->format('Y'));
                $data    = [
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

        // 取得間隔
        $durationYear = self::FILTER_YEARS;
        if(self::FILTER_YEARS <= 2 || self::FILTER_YEARS > 10)
            $durationYear = 2;
        else if(self::FILTER_YEARS % 2 === 1)
            $durationYear = self::FILTER_YEARS - 1;

        // 按分
        $div = intval($durationYear / 2);
        //return array_filter($v, fn(int $year): bool => ($year >= ($this->currentYear - $div) && $year <= ($this->currentYear + $div)), ARRAY_FILTER_USE_KEY);
        $ret=[];
        for($i = $this->currentYear - $div; $i <= $this->currentYear + $div; $i++){
            if(!array_key_exists($i, $v))
                continue;

            $ret = array_merge($ret, $v[$i]);
        }

        return $ret;
    }

    /**
     * 指定キーの値をキーとした、祝祭日名の配列を生成
     *
     * @param  array  $arr
     * @param  CalFormat $format
     * @param  int    $sortFlag
     * @return array
     */
    private function extractor(array $arr, CalFormat $format, int $sortFlag): array {

        $ret = array_column($arr, 'summary', $format->toString());
        ksort($ret, $sortFlag);

        return $ret;
    }

    /**
     * 保存先のフルパスを生成
     *
     * @param  CalType     $type
     * @param  CalFormat   $format
     * @param  string|null $sepDir
     * @param  string|null $extension
     * @return string
     */
    private function makeSavePath(CalType $type, CalFormat $format, ?string $sepDir = null, ?string $extension = null): string {
        return sprintf(
            '%s%s%s%s%s%s',
            $this->saveBasePath,
            ($sepDir !== null) ? (DS . $sepDir) : '',
            ($type !== CalType::BOTH) ? (DS . $type->dirname()) : '',
            DS,
            $format->filename(),
            ($extension !== null) ? ".{$extension}" : ''
        );
    }

    /**
     * ファイル化
     *
     * @return void
     */
    private function putFile(): void {

        // n年間
        {
            // 祝祭日用
            $bothArr = [];

            // 祝祭日は祝日と祭日のデータを使うためこのループでは処理しない
            foreach([CalType::SHU, CalType::SAI] as $calType){
                // 対象となるデータ
                $arr = match($calType){
                    CalType::SHU  => $this->filterYears($this->shukujitsu),
                    CalType::SAI  => $this->filterYears($this->saijitsu)
                };

                $bothArr = array_merge($bothArr, $arr);

                foreach(CalFormat::cases() as $calFormat){
                    $extracted = $this->extractor($arr, $calFormat, ($calFormat === CalFormat::DATE) ? SORT_NATURAL : SORT_NUMERIC);
                    $path = $this->makeSavePath($calType, $calFormat);

                    Functions::putJson($path, $extracted);
                    Functions::putCsv($path, $extracted);
                }

                unset($arr);
            }

            // 祝祭日の処理
            foreach(CalFormat::cases() as $calFormat){
                $extracted = $this->extractor($bothArr, $calFormat, ($calFormat === CalFormat::DATE) ? SORT_NATURAL : SORT_NUMERIC);
                $path = $this->makeSavePath(CalType::BOTH, $calFormat);

                Functions::putJson($path, $extracted);
                Functions::putCsv($path, $extracted);
            }

            unset($bothArr);
        }

        // 年ごとに
        {
            $years = array_unique(array_merge(
                array_keys($this->shukujitsu),
                array_keys($this->saijitsu)
            ));
            sort($years, SORT_NUMERIC);

            foreach($years as $year){
                // 各年の祝日・祭日・祝祭日データ
                $shuArr = $this->shukujitsu[$year] ?? [];
                $saiArr = $this->saijitsu[$year] ?? [];
                $merge = array_merge($saiArr, $shuArr); // FIXME: 祝日優先 (後方上書)

                foreach(CalType::cases() as $calType){
                    // 対象となるデータ
                    $arr = match($calType){
                        CalType::SHU  => $shuArr,
                        CalType::SAI  => $saiArr,
                        CalType::BOTH => $merge
                    };

                    foreach(CalFormat::cases() as $calFormat){
                        $extracted = $this->extractor($arr, $calFormat, ($calFormat === CalFormat::DATE) ? SORT_NATURAL : SORT_NUMERIC);
                        $path = $this->makeSavePath($calType, $calFormat, sepDir: strval($year));

                        Functions::putJson($path, $extracted);
                        Functions::putCsv($path, $extracted);
                    }
                }
            }
        }
    }
}