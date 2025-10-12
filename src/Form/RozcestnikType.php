<?php

namespace App\Form;

use App\Entity\Rozcestnik;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RozcestnikType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('name', TextType::class)
            ->add('email', EmailType::class, [
                'required' => false,
            ])
            ->add('description', TextareaType::class)
            ->add('altitude', IntegerType::class, [
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 2700,
                    'step' => 1,
                ],
            ])
            ->add('latitude', HiddenType::class)
            ->add('longitude', HiddenType::class)
            ->add('fotky', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\All([
                        new Assert\File([
                            'maxSize' => '5M',
                            'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                        ])
                    ])
                ],
                'label' => 'Fotky',
                'multiple' => true,
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rozcestnik::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'rozcestnik_item',
        ]);
    }
}
