<?php

namespace App\Controller\Admin;

use App\Entity\Feedback;
use App\Enum\FeedbackStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FeedbackCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Feedback::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextareaField::new('text', 'Text')
                ->formatValue(function ($value, $entity) {
                    if (strlen($value) > 80) {
                        return substr($value, 0, 80) . '…';
                    }
                    return $value;
                }),
            ChoiceField::new('status', 'Status')
                ->setChoices(array_combine(
                    array_map(fn($case) => $case->label(), FeedbackStatus::cases()),
                    FeedbackStatus::cases()
                ))
                ->renderExpanded(false)
                ->renderAsBadges([
                    'NotRead' => 'secondary',
                    'Accepted' => 'success',
                    'Rejected' => 'danger',
                    'InProgress' => 'warning',
                    'Done' => 'primary',
                ]),
            BooleanField::new('published'),
            DateTimeField::new('createdAt', 'Vytvorené')->onlyOnIndex(),
        ];
    }
}
