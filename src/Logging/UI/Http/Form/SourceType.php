<?php

declare(strict_types=1);

namespace Watchdog\Logging\UI\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Watchdog\Logging\Domain\Source\Platform;
use Watchdog\Logging\Domain\Source\Source;

/**
 * @extends AbstractType<SourceInput>
 */
final class SourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nazwa źródła',
                'empty_data' => '',
                'attr' => ['maxlength' => Source::MAX_NAME_LENGTH, 'placeholder' => 'np. API produkcyjne'],
            ])
            ->add('platform', EnumType::class, ['class' => Platform::class, 'expanded' => true, 'empty_data' => Platform::Http->value]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SourceInput::class, 'csrf_token_id' => 'source']);
    }
}
