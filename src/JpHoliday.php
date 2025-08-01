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

        // タイムゾーン設定
        date_default_timezone_set('Asia/Tokyo');

        // 本年
        $this->currentYear = intval(date('Y'));

        // メジャーバージョン
        $this->majorVersion = '';
        $temp = file_get_contents(dirname(__DIR__) . DS .'version.txt');
        if($temp !== false){
            $this->majorVersion = explode('.', $temp)[0];
        }
        unset($temp);

        // 保存先の基本パス
        $this->saveBasePath = dirname(__DIR__) . DS . self::DISTRIBUTE_DIR_NAME . DS . $this->majorVersion;
        Functions::makeDirectory($this->saveBasePath);

        // 祝祭日ICSを取得するGoogleカレンダーURL
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
     * Fetches calendar data from the Google Calendar URL and stores the raw data.
     *
     * This method retrieves the content of the calendar from the URL specified by
     * the `gCalUrl` property. If the retrieval fails, an exception is thrown. The
     * raw data is cleaned of carriage return characters and stored in the `raw`
     * property.
     *
     * @return void
     * @throws Exception If the calendar data could not be read.
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
     * Processes and summarizes raw holiday data.
     *
     * The method parses the raw holiday data and extracts information such as date, name (summary),
     * and whether the holiday is classified as a public holiday or not. This processed data is then
     * categorized into respective arrays for public holidays (shukujitsu) and other festivals (saijitsu),
     * organized by year.
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
                $dateObj->setTime(0, 0);

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
     * Processes and outputs files containing information regarding holidays and festivals
     * for specific years and different calendar types.
     *
     * Divides the data into various calendar types such as holidays, festivals,
     * and a combination of both. The method executes the following tasks:
     * 1. Processes data grouped by calendar type (holidays and festivals),
     *    combines them, and outputs the combined data as well.
     * 2. Processes and outputs data categorized year by year for holidays, festivals,
     *    and their combination, while ensuring proper priority.
     *
     * @return void
     */
    private function putFile(): void {

        // n年間
        {
            // 祝祭日用
            $bothArr = [];

            // 祝祭日は祝日と祭日のデータを使うためこのループでは処理しない
            foreach([CalType::SHUKUJITSU, CalType::SAIJITSU] as $calType){
                // 対象となるデータ
                $arr = match($calType){
                    CalType::SHUKUJITSU => $this->filterYears($this->shukujitsu),
                    CalType::SAIJITSU   => $this->filterYears($this->saijitsu)
                };

                $bothArr = array_merge($bothArr, $arr);

                $this->filePutter($arr, $calType);

                unset($arr);
            }

            // 祝祭日の処理
            $this->filePutter($bothArr, CalType::BOTH);

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
                $merge  = array_merge($saiArr, $shuArr); // FIXME: 祝日優先 (後方上書)

                foreach(CalType::cases() as $calType){
                    // 対象となるデータ
                    $arr = match($calType){
                        CalType::SHUKUJITSU => $shuArr,
                        CalType::SAIJITSU   => $saiArr,
                        CalType::BOTH       => $merge
                    };

                    $this->filePutter($arr, $calType, $year);
                }
            }
        }
    }

    /**
     * Filters the provided array of years based on a specified time range.
     *
     * The filtering is determined by the `FILTER_YEARS` constant. If `FILTER_YEARS` is
     * less than or equal to 2, or greater than 10, the default range is set to 2. For
     * odd values of `FILTER_YEARS` within the allowed range, it is adjusted to the nearest
     * lower even value. The filtering range is calculated as half of the adjusted `FILTER_YEARS`.
     *
     * @param array $v An associative array where keys represent years and the values are arrays
     *                 containing data associated with the respective year.
     * @return array A filtered array containing the merged values for years within the calculated range.
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

        // 範囲内の各年を一つにまとめる
        $ret = [];
        for($i = $this->currentYear - $div; $i <= $this->currentYear + $div; $i++){
            if(!array_key_exists($i, $v))
                continue;

            $ret = array_merge($ret, $v[$i]);
        }

        return $ret;
    }

    /**
     * Extracts and sorts data from an array based on the given format and sorting flag.
     *
     * @param  array     $arr      The input array to be processed.
     * @param  CalFormat $format   The format used to extract 'summary' data from the array.
     * @param  int       $sortFlag The sorting flag used to determine the sorting behavior.
     * @return array The extracted and sorted data as an associative array.
     */
    private function extractor(array $arr, CalFormat $format, int $sortFlag): array {

        $ret = array_column($arr, 'summary', $format->toString());
        ksort($ret, $sortFlag);

        return $ret;
    }

    /**
     * Processes an array of data and generates output files in different formats (JSON, CSV)
     * based on the given calendar type and year.
     *
     * @param  array    $arr     The input array containing data to process.
     * @param  CalType  $calType The calendar type that determines the directory structure for output files.
     * @param  int|null $year    An optional parameter specifying the year for the output files. Defaults to null.
     * @return void
     */
    private function filePutter(array $arr, CalType $calType, ?int $year = null): void {

        foreach(CalFormat::cases() as $calFormat){
            $extracted = $this->extractor($arr, $calFormat, ($calFormat === CalFormat::DATE) ? SORT_NATURAL : SORT_NUMERIC);
            $path = sprintf(
                '%s%s%s%s',
                $this->saveBasePath,
                ($year !== null) ? (DS . $year) : '',
                ($calType !== CalType::BOTH) ? (DS . $calType->dirname()) : '',
                DS . $calFormat->filename()
            );

            Functions::putJson($path, $extracted);
            Functions::putCsv($path, $extracted);
        }
    }
}