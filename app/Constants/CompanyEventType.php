<?php

namespace App\Constants;

/**
 * Ereignistypen der Betriebsstatistik (#15). column() nennt die Spalte in company_stats_daily.
 */
enum CompanyEventType: string
{
    case PROFILE_VIEW = 'profile_view';
    case PHONE_CLICK = 'phone_click';
    case WEBSITE_CLICK = 'website_click';
    case QUOTE_REQUEST = 'quote_request';
    case LIST_IMPRESSION = 'list_impression';
    case WIDGET_VIEW = 'widget_view';
    case REVIEW_REPLY = 'review_reply';

    public function column(): string
    {
        return match ($this) {
            self::PROFILE_VIEW => 'profile_views',
            self::PHONE_CLICK => 'phone_clicks',
            self::WEBSITE_CLICK => 'website_clicks',
            self::QUOTE_REQUEST => 'quote_requests',
            self::LIST_IMPRESSION => 'list_impressions',
            self::WIDGET_VIEW => 'widget_views',
            self::REVIEW_REPLY => 'review_replies',
        };
    }

    /**
     * Typen, die der Browser ueber den Beacon melden darf.
     *
     * @return list<string>
     */
    public static function beaconValues(): array
    {
        return [
            self::PHONE_CLICK->value,
            self::WEBSITE_CLICK->value,
            self::QUOTE_REQUEST->value,
        ];
    }
}
