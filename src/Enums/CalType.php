<?php
declare(strict_types=1);

namespace JpHoliday\Enums;

/**
 * カレンダー生成タイプ
 */
enum CalType {

    /** 祝日 */
    case SHUKUJITSU;

    /** 祭日 */
    case SAIJITSU;

    /** 祝祭日 */
    case BOTH;

    /**
     * 対応するディレクトリ名
     *
     * @return string|null
     */
    public function dirname(): ?string {
        return match($this){
            self::SHUKUJITSU  => 'shu',
            self::SAIJITSU    => 'sai',
            self::BOTH        => null
        };
    }

    /**
     * キー名変換
     *
     * @return string
     */
    public function toString(): string {
        return match($this){
            self::SHUKUJITSU  => 'shukujitsu',
            self::SAIJITSU    => 'saijitsu',
            self::BOTH        => 'both'
        };
    }
}