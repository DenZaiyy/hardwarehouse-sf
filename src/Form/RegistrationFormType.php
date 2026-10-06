<?php

namespace App\Form;

use App\Entity\User;
use App\EventSubscriber\HoneypotSubscriber;
use App\Service\CspNonceService;
use App\Validator\PasswordRequirements;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\Dropzone\Form\DropzoneType;

class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly CspNonceService $cspNonceService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.registration.email.label',
                'attr' => [
                    'autocomplete' => 'email',
                    'autofocus' => true,
                    'class' => 'w-full',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'user.registration.username.label',
                'attr' => [
                    'autocomplete' => 'username',
                    'class' => 'w-full',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'first_options' => [
                    'label' => 'user.registration.password.label',
                    'attr' => [
                        'class' => 'w-full',
                    ],
                    'toggle' => true,
                ],
                'second_options' => [
                    'label' => 'user.registration.password.confirm.label',
                    'attr' => [
                        'class' => 'w-full',
                    ],
                    'toggle' => true,
                ],
                'help' => 'user.registration.password.help',
                'constraints' => [
                    new PasswordRequirements(),
                ],
            ])
            ->add('avatar', DropzoneType::class, [
                'label' => 'user.registration.avatar.label',
                'help' => 'user.registration.avatar.help',
                'attr' => [
                    'placeholder' => 'user.registration.avatar.placeholder',
                    'class' => 'w-full',
                ],
                'required' => false,
            ])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => [
                    'style' => 'display:none',
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'user.registration.agree_terms.label',
                'label_attr' => [
                    'class' => 'mb-0',
                ],
                'constraints' => [
                    new IsTrue(
                        message: 'user.registration.agree_terms.is_true',
                    ),
                ],
            ])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(
                    message: 'user.registration.captcha.invalid',
                    messageMissingValue: 'user.registration.captcha.invalid',
                ),
                'action_name' => 'homepage',
                'locale' => $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr',
                // La CSP n'exécute que les scripts portant le nonce de la requête : sans lui, le script
                // reCAPTCHA était bloqué, aucun jeton n'était produit et toute inscription refusée
                'script_nonce_csp' => $this->cspNonceService->getNonce(),
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
