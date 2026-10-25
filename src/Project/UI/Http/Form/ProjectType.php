<?php

declare(strict_types=1);

namespace Watchdog\Project\UI\Http\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Watchdog\Project\Domain\Project;

/**
 * @extends AbstractType<ProjectInput>
 */
final class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Nazwa projektu',
            'empty_data' => '',
            'attr' => ['maxlength' => Project::MAX_NAME_LENGTH, 'placeholder' => 'Nazwa nowego projektu'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ProjectInput::class, 'csrf_token_id' => 'project']);
    }
}
