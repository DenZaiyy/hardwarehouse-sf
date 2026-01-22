<?php

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangePasswordFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank(
                        message: $this->translator->trans('user.changePassword.current.constraint.not_blank')
                    )
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
                'label' => $this->translator->trans('user.update.changePassword.current.label'),
                'help' => $this->translator->trans('user.update.changePassword.current.help'),
                'required' => true,
                'toggle' => true
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => true,
                'first_options' => [
                    'label' => $this->translator->trans('user.update.changePassword.new.first.label'),
                    'help' => $this->translator->trans('user.update.changePassword.new.first.help'),
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'toggle' => true,
                    'constraints' => [
                        new NotBlank(message: $this->translator->trans('user.update.changePassword.new.constraint.not_blank')),
                        new Length(
                            min: 6,
                            max: 4096,
                            minMessage: $this->translator->trans('user.update.changePassword.new.constraint.length.minMessage'),
                        ),
                        new PasswordStrength(),
                        new NotCompromisedPassword(),
                        new Regex(
                            pattern: '/^(?=.*\d)(?=.*[!-\/:-@[-`{-~À-ÿ§µ²°£])(?=.*[a-z])(?=.*[A-Z])(?=.*[A-Za-z]).{12,32}$/',
                            message: "Le mot de passe doit contenir au moins 1 majuscule, 1 minuscule, 1 nombre, 1 caractère spéciale et doit faire au moins 12 caractères.",
                            match: true,
                        )
                    ]
                ],
                'second_options' => [
                    'label' => $this->translator->trans('user.update.changePassword.new.second.label'),
                    'help' => $this->translator->trans('user.update.changePassword.new.second.help'),
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                    'toggle' => true
                ],
                'invalid_message' => $this->translator->trans('user.update.changePassword.new.invalid.message'),
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('user.update.changePassword.submit.label'),
                'attr' => [
                    'class' => 'w-full'
                ]
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, $this->attachTimestamps(...))
        ;
    }

    private function attachTimestamps(PostSubmitEvent $event): void
    {
        $data = $event->getData();

        if (!($data instanceof User)) {
            return;
        }

        $data->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => [
                'class' => 'flex flex-col gap-4',
            ]
        ]);
    }
}
