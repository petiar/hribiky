<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum FeedbackStatus: string
{
    case NotRead = 'NotRead';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case InProgress = 'InProgress';
    case Done = 'Done';

    public function labelKey(): string {
        return match ($this) {
            self::NotRead => 'feedback.status_notread',
            self::Accepted => 'feedback.status_accepted',
            self::Rejected => 'feedback.status_rejected',
            self::InProgress => 'feedback.status_inprogress',
            self::Done => 'feedback.status_done',
        };
    }
}
