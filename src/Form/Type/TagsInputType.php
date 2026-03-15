<?php

namespace App\Form\Type;

use App\Form\DataTransformer\TagsTransformer;
use App\Repository\TagRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagsInputType extends AbstractType
{
    public function __construct(private TagRepository $tagRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new TagsTransformer($this->tagRepository));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['placeholder' => 'turistika, slovensko, hory'],
            'help' => 'Tagy oddelené čiarkou. Nový tag sa automaticky vytvorí.',
            'by_reference' => false,
        ]);

        // EasyAdmin posiela tieto options pre asociačné polia — akceptujeme ich, ignorujeme
        $resolver->setDefined(['class', 'multiple', 'query_builder']);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}