<?php

namespace App\Form\Checkout;

use App\DTO\Checkout\AddressData;
use App\Enum\CountryList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CheckoutAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class)
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('address1', TextType::class)
            ->add('postcode', TextType::class)
            ->add('city', TextType::class)
            ->add('country', ChoiceType::class, [
                'choices' => $this->getCountryChoices(),
            ])
        ;
    }

    private function getCountryChoices(): array
    {
        $choices = [];

        foreach (CountryList::cases() as $country) {
            $choices[$country->name] = $country->value;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressData::class,
        ]);
    }
}
