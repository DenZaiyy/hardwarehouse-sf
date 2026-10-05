<?php

namespace App\Form\Checkout;

use App\DTO\Checkout\GuestIdentityData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GuestIdentityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', ChoiceType::class, [
                'label' => 'checkout.form.title',
                'choices' => [
                    'checkout.form.mr' => 'mr',
                    'checkout.form.mrs' => 'mrs',
                ],
                'required' => false,
                'expanded' => true,
                // Civilité facultative, sans case « aucune » à côté de M. et Mme
                'placeholder' => false,
            ])
            ->add('firstName', TextType::class, ['label' => 'checkout.form.first_name'])
            ->add('lastName', TextType::class, ['label' => 'checkout.form.last_name'])
            ->add('email', EmailType::class, ['label' => 'checkout.form.email'])
            ->add('password', PasswordType::class, [
                'label' => 'checkout.form.password',
                'required' => false,
                'attr' => ['placeholder' => 'checkout.form.password_placeholder'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GuestIdentityData::class,
        ]);
    }
}
