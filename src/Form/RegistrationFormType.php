<?php

namespace App\Form;

use App\Entity\User;
use App\EventSubscriber\HoneypotSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\UX\Dropzone\Form\DropzoneType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.registration.email.label',
                'attr' => [
                    'autocomplete' => 'email',
                    'autofocus' => true,
                    'class' => 'w-full'
                ],
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('username', TextType::class, [
                'label' => 'user.registration.username.label',
                'attr' => [
                    'autocomplete' => 'username',
                    'class' => 'w-full'
                ],
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password'
                ],
                'first_options' => [
                    'label' => 'user.registration.password.label',
                    'attr' => [
                        'class' => 'w-full'
                    ],
                    'toggle' => true
                ],
                'second_options' => [
                    'label' => 'user.registration.password.confirm.label',
                    'attr' => [
                        'class' => 'w-full'
                    ]
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'user.registration.password.not_blank',
                    ),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'user.registration.password.min_length',
                    ),
                    new Regex(
                        pattern: '/^(?=.*\d)(?=.*[!-\/:-@[-`{-~À-ÿ§µ²°£])(?=.*[a-z])(?=.*[A-Z])(?=.*[A-Za-z]).{12,32}$/',
                        message: "Le mot de passe doit contenir au moins 1 majuscule, 1 minuscule, 1 nombre, 1 caractère spéciale et doit faire au moins 12 caractères.",
                        match: true,
                    )
                ],
            ])
            ->add('avatar', DropzoneType::class, [
                'label' => 'user.registration.avatar.label',
                'attr' => [
                    'placeholder' => 'user.registration.avatar.placeholder',
                    'class' => 'w-full'
                ],
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => [
                    'style' => 'display:none'
                ]
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'user.registration.agree_terms.label',
                'label_attr' => [
                    'class' => 'mb-0'
                ],
                'constraints' => [
                    new IsTrue(
                        message: 'user.registration.agree_terms.is_true',
                    ),
                ],
            ])
            ->addEventSubscriber(new HoneypotSubscriber())
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default'],
        ]);
    }
}
