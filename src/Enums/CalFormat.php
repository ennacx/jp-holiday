<?php
declare(strict_types=1);

namespace JpHoliday\Enums;

enum CalFormat {

    case DATE;

    case TIMESTAMP;

    public function filename(): string {
        return match($this){
            self::DATE      => 'date',
            self::TIMESTAMP => 'ts'
        };
    }

    public function toString(): string {
        return match($this){
            self::DATE      => 'date',
            self::TIMESTAMP => 'timestamp'
        };
    }
}
