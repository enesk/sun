<?php

namespace App\Constants;

enum CompanyInquiryStatus: string
{
    case NEW = 'new';
    case READ = 'read';
    case ANSWERED = 'answered';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('Neu'),
            self::READ => __('Gelesen'),
            self::ANSWERED => __('Beantwortet'),
            self::DONE => __('Erledigt'),
        };
    }
}
