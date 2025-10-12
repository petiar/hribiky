<?php

namespace App\Enum;

enum FeedbackStatus: string
{
    case NotRead = 'NotRead';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case InProgress = 'InProgress';
    case Done = 'Done';

    public function label(): string {
        return match ($this) {
            self::NotRead => 'Neprečítané',
            self::Accepted => 'Prijaté',
            self::Rejected => 'Toto nebudem robiť',
            self::InProgress => 'Toto už robím',
            self::Done => 'Hotovo',
        };
    }
}
