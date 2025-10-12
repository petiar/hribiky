<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('text', TextareaType::class, [
                'label' => 'Pokojne sa rozpíšte...',
                'attr' => ['rows' => 5, 'class' => 'form-control'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Odoslať',
                'attr' => ['class' => 'btn btn-primary mt-2'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Feedback::class,
        ]);
    }
}
