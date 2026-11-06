<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AsCommand(name: 'app:repro')]
final class ReproCommand extends Command
{
    public function __construct(private readonly FormFactoryInterface $formFactory)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Two "steps" of a multi step form. Both inherit the data of the root form,
        // so their "email" fields share the property path "[email]".
        // Only the active step ("details") is enabled, the inactive step ("personal_infos") is disabled.
        $builder = $this->formFactory->createBuilder(FormType::class, []);

        $builder->add('details', FormType::class, ['inherit_data' => true]);
        $builder->get('details')->add('email', EmailType::class, ['constraints' => [new NotBlank()]]);

        $builder->add('personal_infos', FormType::class, ['inherit_data' => true, 'disabled' => true]);
        $builder->get('personal_infos')->add('email', EmailType::class);

        $form = $builder->getForm();

        // The active step "details" is submitted with an empty (NotBlank) email.
        $form->submit(['details' => ['email' => '']]);

        $output->writeln('isValid: ' . ($form->isValid() ? 'true' : 'false'));

        foreach ($this->collectErrors($form) as $path => $message) {
            $output->writeln(sprintf('  error on "%s": %s', $path, $message));
        }

        return $form->isValid() ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return iterable<string, string>
     */
    private function collectErrors(FormInterface $form, string $path = 'root'): iterable
    {
        foreach ($form->getErrors() as $error) {
            \assert($error instanceof FormError);

            yield $path => $error->getMessage();
        }

        foreach ($form->all() as $name => $child) {
            yield from $this->collectErrors($child, $path . '.' . $name);
        }
    }
}
