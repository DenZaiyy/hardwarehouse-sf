<?php

namespace App\Form\User;

use App\Config\CountryList;
use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserAddressFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => $this->translator->trans('user.address.label.label'),
            ])
            ->add('firstname', TextType::class, [
                'label' => $this->translator->trans('user.address.firstname.label'),
                'attr' => [
                    'autocomplete' => 'given-name',
                ]
            ])
            ->add('lastname', TextType::class, [
                'label' => $this->translator->trans('user.address.lastname.label'),
                'attr' => [
                    'autocomplete' => 'family-name',
                ]
            ])
            ->add('address', TextType::class, [
                'label' => $this->translator->trans('user.address.address.label'),
                'attr' => [
                    'autocomplete' => 'address-line1',
                ]
            ])
            ->add('cp', TextType::class, [
                'label' => $this->translator->trans('user.address.cp.label'),
                'attr' => [
                    'autocomplete' => 'postal-code',
                ]
            ])
            ->add('city', TextType::class, [
                'label' => $this->translator->trans('user.address.city.label'),
                'attr' => [
                    'autocomplete' => 'city',
                ]
            ])
            ->add('country', EnumType::class, [
                'label' => $this->translator->trans('user.address.country.label'),
                'class' => CountryList::class,
                'attr' => [
                    'autocomplete' => 'country',
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => $this->translator->trans('user.address.save.label'),
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, $this->attachTimestamps(...))
        ;
    }

    private function attachTimestamps(PostSubmitEvent $event): void
    {
        $data = $event->getData();

        if (!($data instanceof Address)) {
            return;
        }

        $data->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        if (!$data->getId()) {
            $data->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
