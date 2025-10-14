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

    public function label(TranslatorInterface $t): string {
        return match ($this) {
            self::NotRead => $t->trans('feedback.status_notread'),
            self::Accepted => $t->trans('feedback.status_accepted'),
            self::Rejected => $t->trans('feedback.status_rejected'),
            self::InProgress => $t->trans('feedback.status_inprogress'),
            self::Done => $t->trans('feedback.status_done'),
        };
    }
}
