<?php

declare(strict_types=1);

namespace App\Form\Contact;

use App\Dto\Form\Contact\ContactFormDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'contact.form.name.label',
                'attr' => [
                    'placeholder' => 'contact.form.name.placeholder',
                    'class' => 'w-full',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'contact.form.email.label',
                'attr' => [
                    'placeholder' => 'contact.form.email.placeholder',
                    'class' => 'w-full',
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'contact.form.subject.label',
                'attr' => [
                    'placeholder' => 'contact.form.subject.placeholder',
                    'class' => 'w-full',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message.label',
                'attr' => [
                    'placeholder' => 'contact.form.message.placeholder',
                    'class' => 'w-full',
                    'rows' => 5,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'contact.form.submit.label',
                'attr' => [
                    'class' => 'mt-4',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactFormDto::class
        ]);
    }
}
