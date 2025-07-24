<?php
declare(strict_types=1);

namespace JpHoliday\Enums;

/**
 * カレンダー出力形式
 */
enum CalFormat {

    /** YYYY-MM-DD形式 */
    case DATE;

    /** UNIXタイムスタンプ形式 */
    case TIMESTAMP;

    /**
     * 対応するファイル名
     *
     * @return string
     */
    public function filename(): string {
        return match($this){
            self::DATE      => 'date',
            self::TIMESTAMP => 'ts'
        };
    }

    /**
     * キー名変換
     *
     * @return string
     */
    public function toString(): string {
        return match($this){
            self::DATE      => 'date',
            self::TIMESTAMP => 'timestamp'
        };
    }
}
