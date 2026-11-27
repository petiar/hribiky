<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

class FeedbackType extends AbstractType
{
    /** Názov skrytého poľa (honeypot) — zámerne bežný, aby ho roboti vyplnili. */
    public const HONEYPOT_FIELD = 'website';
    /** Skryté pole s podpísanou časovou značkou (SpamGuard). */
    public const TOKEN_FIELD = 'started';

    public function __construct(private TranslatorInterface $translator)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextareaType::class, [
                'label' => $this->translator->trans('feedback.text_label'),
                'attr' => ['rows' => 6, 'class' => 'form-control', 'maxlength' => 5000],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 5, max: 5000),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('feedback.email_label'),
                'required' => false,
                'help' => $this->translator->trans('feedback.email_help'),
                'attr' => ['class' => 'form-control', 'autocomplete' => 'email', 'inputmode' => 'email'],
                'constraints' => [new Assert\Email(), new Assert\Length(max: 255)],
            ])
            // Ochrana proti spamu (viď SpamGuard) — nie sú súčasťou entity.
            ->add(self::HONEYPOT_FIELD, TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Website',
                'row_attr' => ['class' => 'hp-field', 'aria-hidden' => 'true'],
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ])
            ->add(self::TOKEN_FIELD, HiddenType::class, [
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('submit'),
                'attr' => ['class' => 'btn btn-primary mt-2'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Feedback::class,
        ]);
    }
}
