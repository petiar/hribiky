<?php

namespace App\Form;

use App\Entity\Mushroom;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MushroomPublicEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $opts): void
    {
        $b->add('description', TextareaType::class, [
            'label' => 'Popis',
            'attr' => [
                'rows' => 8,
                'placeholder' => 'Doplňte podrobnejší popis...',
                'class' => 'form-control',
            ],
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mushroom::class,
        ]);
    }
}
