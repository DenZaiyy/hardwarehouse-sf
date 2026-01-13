<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

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
                ],
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('username', TextType::class, [
                'label' => 'user.registration.username.label',
                'attr' => [
                    'autocomplete' => 'username'
                ],
                'constraints' => [
                    new NotBlank()
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'first_options' => ['label' => 'user.registration.password.label'],
                'second_options' => ['label' => 'user.registration.password.confirm.label'],
                'constraints' => [
                    new NotBlank(
                        message: 'user.registration.password.not_blank',
                    ),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'user.registration.password.min_length',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'user.registration.agree_terms.label',
                'constraints' => [
                    new IsTrue(
                        message:  'user.registration.agree_terms.is_true',
                    ),
                ],
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
