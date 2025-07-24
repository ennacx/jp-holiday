<?php
declare(strict_types=1);

namespace JpHoliday\Enums;

enum CalType {

    case SHU;

    case SAI;

    case BOTH;

    public function dirname(): ?string {
        return match($this){
            self::SHU => 'shu',
            self::SAI => 'sai',
            self::BOTH => null
        };
    }
}