<?php

namespace App\Enums;

/**
 * Suhbat holatlari (States)
 */
class ConversationState
{
    const IDLE = 'IDLE';  // Bo'sh holat

    // Prezentatsiya yaratish holatlari
    const AWAITING_UNIVERSITY = 'AWAITING_UNIVERSITY';  // Universitet kutilmoqda
    const AWAITING_DIRECTION = 'AWAITING_DIRECTION';    // Yo'nalish kutilmoqda
    const AWAITING_GROUP = 'AWAITING_GROUP';            // Guruh kutilmoqda
    const AWAITING_PLACEMENT = 'AWAITING_PLACEMENT';    // Ma'lumot joyi kutilmoqda
    const AWAITING_TOPIC = 'AWAITING_TOPIC';            // Mavzu kutilmoqda
    const AWAITING_PAGES = 'AWAITING_PAGES';            // Sahifalar soni kutilmoqda
    const AWAITING_FORMAT = 'AWAITING_FORMAT';          // Format kutilmoqda
    const GENERATING = 'GENERATING';                     // Yaratilmoqda

    /**
     * Barcha holatlar ro'yxati
     */
    public static function all()
    {
        return [
            self::IDLE,
            self::AWAITING_UNIVERSITY,
            self::AWAITING_DIRECTION,
            self::AWAITING_GROUP,
            self::AWAITING_PLACEMENT,
            self::AWAITING_TOPIC,
            self::AWAITING_PAGES,
            self::AWAITING_FORMAT,
            self::GENERATING,
        ];
    }
}
