<?php

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Dropzone\Form\DropzoneType;

class UpdateInfosFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('user.update.infos.email.label'),
            ])
            ->add('username', TextType::class, [
                'label' => $this->translator->trans('user.update.infos.username.label'),
            ])
            ->add('avatarFile', DropzoneType::class, [
                'label' => $this->translator->trans('user.update.infos.avatar.label'),
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => $this->translator->trans('user.update.infos.submit.label'),
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, $this->attachTimestamps(...))
        ;
    }

    private function attachTimestamps(PostSubmitEvent $event): void
    {
        $data = $event->getData();

        if (!$data instanceof User) {
            return;
        }

        $data->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default'],
        ]);
    }
}
