<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class HoneypotSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => 'checkHoneypot',
        ];
    }

    public function checkHoneypot(FormEvent $event): void
    {
        $form = $event->getForm();

        if ($form->has('website')) {
            $value = $form->get('website')->getData();

            if (!empty($value)) {
                $form->addError(new FormError('Bot détecté'));
            }
        }
    }
}
