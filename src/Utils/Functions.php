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
}