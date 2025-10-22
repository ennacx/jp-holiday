<?php
namespace JpHoliday\Utils;

use Exception;

/**
 * Class Http
 *
 * This class provides methods for fetching data from a URL with support for caching using ETag and Last-Modified headers.
 * Responses can be cached locally to reduce redundant HTTP requests and improve performance.
 */
class Http {

    /** @var string Googleカレンダーの祝祭日ICSを取得するURL */
    private const string HOLIDAY_BASE_URL = 'https://calendar.google.com/calendar/ical/%s/public/full.ics';

    /** @var string Googleカレンダーの祝祭日ICSを取得するID */
    private const string HOLIDAY_ID = 'ja.japanese#holiday@group.v.calendar.google.com';

    /** @var string キャッシュ化したボディファイル名 */
    private const string CACHE_BODY_NAME = 'gcal_raw.ics';
    /** @var string キャッシュ化したハッシュファイル名 */
    private const string CACHE_HASH_NAME = 'gcal_raw.sha256';

    /**
     * Constructs and returns a formatted URL string based on predefined constants.
     *
     * @return string The formatted URL string.
     */
    public static function getUrl(): string {
        return sprintf(self::HOLIDAY_BASE_URL, urlencode(self::HOLIDAY_ID));
    }

    /**
     * Fetches data from a given URL and caches the response locally. Bypasses
     * the download if the remote content has not changed based on a hash comparison.
     *
     * @param string $cachePath  The path where the local cache is stored.
     * @param int    $timeoutSec The timeout duration for the HTTP request in seconds. Defaults to 10.
     * @return array An associative array containing the HTTP status code and the response body. If the content has not changed, status is 304 and body is empty.
     * @throws Exception If the cache path is invalid, an HTTP error occurs, or the response body is empty.
     */
    public static function getIcsWithLocalCache(string $cachePath, int $timeoutSec = 10): array {

        if(!file_exists($cachePath) || !is_dir($cachePath))
            throw new Exception("Cache path does not exist: {$cachePath}");

        $cacheBody = $cachePath . DS . self::CACHE_BODY_NAME;
        $cacheHash = $cachePath . DS . self::CACHE_HASH_NAME;

        $oldBody = (is_file($cacheBody)) ? file_get_contents($cacheBody) : '';
        $oldHash = (is_file($cacheHash)) ? trim(file_get_contents($cacheHash)) : null;

        // cURLでダウンロード
        try{
            $ch = curl_init(self::getUrl());
            if($ch === false)
                throw new Exception('Failed to initialize cURL');

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeoutSec,
                CURLOPT_TIMEOUT        => $timeoutSec,
                CURLOPT_USERAGENT      => sprintf('jp-holiday/1.0 (+%s)', HOST_URL),
            ]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        } catch(Exception $e){
            throw new Exception("cURL error: {$e->getMessage()}");
        } finally{
            if(isset($ch) && $ch !== false)
                curl_close($ch);
        }

        if($status < 200 || $status >= 300)
            throw new Exception("HTTP error: {$status}");
        else if($body === false || $body === '')
            throw new Exception('Empty ICS body');

        // 揺らぎ除去してハッシュ化
        $normalized = self::normalizeIcs($body);
        $newHash    = hash('sha256', $normalized);

        // 内容が完全一致 → スキップ
        if($oldHash === $newHash)
            return ['status' => 304, 'body' => $normalized];

        // 更新された場合のみ保存
        file_put_contents($cacheBody, $normalized);
        file_put_contents($cacheHash, trim($newHash), LOCK_EX);

        return ['status' => 200, 'body' => $normalized];
    }

    /**
     * Normalizes the content of an iCalendar (ICS) string by removing specific lines,
     * eliminating duplicates, and standardizing the line endings.
     *
     * @param string $body The raw content of the iCalendar (ICS) file as a string.
     * @return string The normalized iCalendar (ICS) content as a string.
     */
    private static function normalizeIcs(string $body): string {

        $lines = preg_split('/\r\n|\n|\r/', $body);

        $filtered = [];
        $vTimezoneSkip = false;
        foreach($lines as $line){
            // VTIMEZONEブロックの中身が微妙に揺れることがあるので、ここは丸ごと落とす
            if(preg_match('/^BEGIN:VTIMEZONE/i', $line)){
                $vTimezoneSkip = true;
                continue;
            } else if(preg_match('/^END:VTIMEZONE/i', $line)){
                $vTimezoneSkip = false;
                continue;
            }

            // 変動する可能性の高い行をスキップ
            if($vTimezoneSkip || preg_match('/^(DTSTAMP|PRODID|LAST-MODIFIED|CREATED|SEQUENCE):/i', $line))
                continue;

            $filtered[] = rtrim($line);
        }

        // 空行を整理
        $filtered = array_filter($filtered, fn($v): bool => ($v !== ''));

        // 改行コードを統一して返却
        return implode("\n", $filtered);
    }
}