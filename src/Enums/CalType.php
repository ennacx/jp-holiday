<?php
declare(strict_types=1);

namespace JpHoliday\Enums;

/**
 * カレンダー生成タイプ
 */
enum CalType {

    /** 祝日 */
    case SHU;

    /** 祭日 */
    case SAI;

    /** 祝祭日 */
    case BOTH;

    /** 対応するディレクトリ名 */
    public function dirname(): ?string {
        return match($this){
            self::SHU => 'shu',
            self::SAI => 'sai',
            self::BOTH => null
        };
    }
}