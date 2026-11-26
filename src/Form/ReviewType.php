<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Rating;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Avis d'un client sur un produit acheté : une note de 1 à 5 étoiles et un commentaire facultatif (données : Rating).
 */
final class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label' => 'product.page.reviews.form.note',
                'choices' => [1.0, 2.0, 3.0, 4.0, 5.0],
                'choice_value' => static fn (?float $note): string => null === $note ? '' : (string) (int) $note,
                'choice_label' => static fn (): string => 'product.page.reviews.form.stars',
                'choice_translation_parameters' => static fn (float $note): array => ['%count%' => (int) $note],
                'expanded' => true,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'product.page.reviews.form.comment',
                'required' => false,
                'attr' => ['maxlength' => 1000, 'rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Rating::class]);
    }
}
