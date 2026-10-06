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
            ->add('label', TextType::class, ['label' => 'checkout.form.label'])
            ->add('firstName', TextType::class, ['label' => 'checkout.form.first_name'])
            ->add('lastName', TextType::class, ['label' => 'checkout.form.last_name'])
            ->add('address1', TextType::class, ['label' => 'checkout.form.address'])
            ->add('postcode', TextType::class, ['label' => 'checkout.form.postcode'])
            ->add('city', TextType::class, ['label' => 'checkout.form.city'])
            ->add('country', ChoiceType::class, [
                'label' => 'checkout.form.country',
                'choices' => $this->getCountryChoices(),
                // Le code ISO reste la valeur enregistrée ; le client lit le nom du pays
                'choice_label' => static fn (string $code): CountryList => CountryList::from($code),
            ])
        ;
    }

    /**
     * @return array<string, string>
     */
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
