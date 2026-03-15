<?php

namespace App\Form\DataTransformer;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\DataTransformerInterface;

class TagsTransformer implements DataTransformerInterface
{
    public function __construct(private TagRepository $tagRepository)
    {
    }

    public function transform($value): string
    {
        if ($value === null) {
            return '';
        }

        return implode(', ', $value->map(fn($t) => $t->getName())->toArray());
    }

    public function reverseTransform($value): ArrayCollection
    {
        $collection = new ArrayCollection();

        if (!$value) {
            return $collection;
        }

        foreach (array_filter(array_map('trim', explode(',', $value))) as $name) {
            $collection->add($this->tagRepository->findOrCreate($name));
        }

        return $collection;
    }
}