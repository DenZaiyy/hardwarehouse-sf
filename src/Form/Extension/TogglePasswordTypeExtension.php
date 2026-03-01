<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TogglePasswordTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly ?TranslatorInterface $translator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [PasswordType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // TODO: Restyling button for better integration with Tailwind
        $resolver->setDefaults([
            'toggle' => false,
            'hidden_label' => 'Hide',
            'visible_label' => 'Show',
            'hidden_icon' => 'Default',
            'visible_icon' => 'Default',
            'button_classes' => ['toggle-password-button'],
            'toggle_container_classes' => ['toggle-password-container'],
            'toggle_translation_domain' => null,
            'use_toggle_form_theme' => true,
        ]);

        $resolver->setNormalizer(
            'toggle_translation_domain',
            static fn (Options $options, $labelTranslationDomain) => $labelTranslationDomain ?? $options['translation_domain'],
        );

        $resolver->setAllowedTypes('toggle', ['bool']);
        $resolver->setAllowedTypes('hidden_label', ['string', TranslatableMessage::class, 'null']);
        $resolver->setAllowedTypes('visible_label', ['string', TranslatableMessage::class, 'null']);
        $resolver->setAllowedTypes('hidden_icon', ['string', 'null']);
        $resolver->setAllowedTypes('visible_icon', ['string', 'null']);
        $resolver->setAllowedTypes('button_classes', ['string[]']);
        $resolver->setAllowedTypes('toggle_container_classes', ['string[]']);
        $resolver->setAllowedTypes('toggle_translation_domain', ['string', 'bool', 'null']);
        $resolver->setAllowedTypes('use_toggle_form_theme', ['bool']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var bool $toggle */
        $toggle = $options['toggle'];
        $view->vars['toggle'] = $toggle;

        if (!$toggle) {
            return;
        }

        /** @var bool $useToggleFormTheme */
        $useToggleFormTheme = $options['use_toggle_form_theme'];
        if ($useToggleFormTheme) {
            /** @var list<string> $blockPrefixes */
            $blockPrefixes = $view->vars['block_prefixes'];
            array_splice($blockPrefixes, -1, 0, 'toggle_password');
            $view->vars['block_prefixes'] = $blockPrefixes;
        }

        $controllerName = 'toggle-password';

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];
        $existingController = isset($attr['data-controller']) && \is_string($attr['data-controller'])
            ? $attr['data-controller']
            : '';
        $attr['data-controller'] = trim(\sprintf('%s %s', $existingController, $controllerName));

        /** @var string|TranslatableMessage|null $hiddenLabel */
        $hiddenLabel = $options['hidden_label'];
        /** @var string|TranslatableMessage|null $visibleLabel */
        $visibleLabel = $options['visible_label'];
        /** @var string|null $hiddenIcon */
        $hiddenIcon = $options['hidden_icon'];
        /** @var string|null $visibleIcon */
        $visibleIcon = $options['visible_icon'];
        /** @var list<string> $buttonClasses */
        $buttonClasses = $options['button_classes'];
        /** @var string|bool|null $toggleTranslationDomain */
        $toggleTranslationDomain = $options['toggle_translation_domain'];

        $translationDomain = \is_string($toggleTranslationDomain) ? $toggleTranslationDomain : null;

        if (false !== $toggleTranslationDomain) {
            $controllerValues['hidden-label'] = $this->translateLabel($hiddenLabel, $translationDomain);
            $controllerValues['visible-label'] = $this->translateLabel($visibleLabel, $translationDomain);
        } else {
            $controllerValues['hidden-label'] = $this->labelToString($hiddenLabel);
            $controllerValues['visible-label'] = $this->labelToString($visibleLabel);
        }

        $controllerValues['hidden-icon'] = $hiddenIcon;
        $controllerValues['visible-icon'] = $visibleIcon;
        $controllerValues['button-classes'] = json_encode($buttonClasses, \JSON_THROW_ON_ERROR);

        foreach ($controllerValues as $name => $value) {
            $attr[\sprintf('data-%s-%s-value', $controllerName, $name)] = $value;
        }

        $view->vars['attr'] = $attr;

        /** @var list<string> $toggleContainerClasses */
        $toggleContainerClasses = $options['toggle_container_classes'];
        $view->vars['toggle_container_classes'] = $toggleContainerClasses;
    }

    private function translateLabel(string|TranslatableMessage|null $label, ?string $translationDomain): ?string
    {
        if (null === $label) {
            return null;
        }

        if ($label instanceof TranslatableMessage) {
            return null !== $this->translator
                ? $label->trans($this->translator)
                : $label->getMessage();
        }

        return null !== $this->translator
            ? $this->translator->trans($label, domain: $translationDomain)
            : $label;
    }

    private function labelToString(string|TranslatableMessage|null $label): ?string
    {
        if (null === $label) {
            return null;
        }

        return $label instanceof TranslatableMessage
            ? $label->getMessage()
            : $label;
    }
}
