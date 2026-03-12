<?php

namespace App\Form\Checkout;

use App\DTO\Checkout\DeliveryChoiceData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DeliveryChoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, array{id: int, label: string}> $carriers */
        $carriers = $options['carriers'];
        $choices = [];

        foreach ($carriers as $carrier) {
            $choices[$carrier['label']] = $carrier['id'];
        }

        $builder->add('carrierId', ChoiceType::class, [
            'choices' => $choices,
            'expanded' => true,
            'multiple' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DeliveryChoiceData::class,
            'carriers' => [],
        ]);

        $resolver->setAllowedTypes('carriers', 'array');
    }
}
