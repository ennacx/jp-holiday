<?php
declare(strict_types=1);

namespace JpHoliday\Utils;

use Generator;

class Functions {

    /**
     * イテレーブルな引数からデータをYieldする
     *
     * @param  iterable  $i
     * @return Generator
     */
    public static function getGenerator(iterable $i): Generator {
        foreach($i as $k => $v)
            yield $k => $v;
    }

    /**
     * ディレクトリ作成
     *
     * @param  string  $path
     * @param  int     $mode
     * @param  boolean $recursive
     * @return boolean
     */
    public static function makeDirectory(string $path, int $mode = 0755, bool $recursive = true): bool {

        if(!file_exists($path) || !is_dir($path))
            return mkdir($path, $mode, $recursive);

        return true;
    }

    /**
     * JSON化
     *
     * @param  string $path
     * @param  array  $arr
     * @param  string|null $extension
     * @return void
     */
    public static function putJson(string $path, array $arr, ?string $extension = 'json'): void {

        $fullPath = sprintf('%s%s', $path, (!empty($extension)) ? ".{$extension}" : '');

        self::makeDirectory(dirname($fullPath));

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
    public static function putCsv(string $path, array $arr, string $extension = 'csv'): void {

        $fullPath = sprintf('%s%s', $path, (!empty($extension)) ? ".{$extension}" : '');

        self::makeDirectory(dirname($fullPath));

        try{
            $fp = fopen($fullPath, 'w');

            foreach(Functions::getGenerator($arr) as $k => $v){
                fwrite($fp, sprintf("%s\n", implode(',', array_map(fn($v): string => "\"{$v}\"", [$k, $v]))));
            }
        } finally{
            fclose($fp);
        }
    }

    /**
     * Sorts a multidimensional array first by its keys and then by inner arrays based on `timestamp` values.
     *
     * @param array &$arr The array to be sorted. The function modifies this array in place.
     * @return void
     */
    public static function sorter(array &$arr): void {

        ksort($arr);
        foreach($arr as $year => &$v){
            usort($v, fn($a, $b): int => strcmp($a['timestamp'], $b['timestamp']));
        }
    }
}