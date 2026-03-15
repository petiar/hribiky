<?php

namespace App\Form;

use App\Entity\BlogPost;
use App\Entity\MushroomArticleLink;
use App\Repository\BlogPostRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MushroomArticleLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Názov článku',
                'empty_data' => '',
                'attr' => ['placeholder' => 'Titulok, ktorý sa zobrazí na stránke hríbika'],
            ])
            ->add('url', TextType::class, [
                'label' => 'URL',
                'empty_data' => '',
                'attr' => ['placeholder' => 'https://... alebo /blog/nazov-clanku'],
            ])
            ->add('blogPost', EntityType::class, [
                'class' => BlogPost::class,
                'label' => 'Interný blogpost',
                'required' => false,
                'placeholder' => '— externý článok —',
                'choice_label' => 'title',
                'query_builder' => fn(BlogPostRepository $repo) => $repo->createQueryBuilder('b')->orderBy('b.createdAt', 'DESC'),
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MushroomArticleLink::class,
        ]);
    }
}