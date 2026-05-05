<?php
// app/code/SVExtensions/DynamicLabel/Console/Command/DeleteCustomVariablesCommand.php

declare(strict_types=1);

namespace SVExtensions\DynamicLabel\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\App\ResourceConnection;
use Magento\Variable\Model\ResourceModel\Variable\CollectionFactory;
use Magento\Variable\Model\ResourceModel\Variable as VariableResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCustomVariablesCommand extends Command
{
    private const COMMAND_NAME  = 'svextensions:dynamiclabel:delete-variables';
    private const OPTION_FORCE  = 'force';

    public function __construct(
        private readonly CollectionFactory  $collectionFactory,
        private readonly VariableResource   $variableResource,
        private readonly ResourceConnection $resourceConnection,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Delete ALL custom variables (variable + variable_value tables)')
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isForce = (bool) $input->getOption(self::OPTION_FORCE);
        // php bin/magento svextensions:dynamiclabel:delete-variables
        // ── Safety confirmation unless --force passed ───────────────────
        if (!$isForce) {
            $helper   = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>This will DELETE ALL custom variables. Are you sure? (y/N): </question>',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Cli::RETURN_SUCCESS;
            }
        }

        $connection = $this->resourceConnection->getConnection();

        try {
            // ── Step 1: Truncate variable_value first (FK child table) ──
            $valueTable = $this->resourceConnection->getTableName('variable_value');
            $deletedValues = $connection->delete($valueTable);
            $output->writeln(sprintf(
                '<info>Deleted %d row(s) from variable_value.</info>',
                $deletedValues
            ));

            // ── Step 2: Truncate variable (FK parent table) ─────────────
            $variableTable = $this->resourceConnection->getTableName('variable');
            $deletedVars = $connection->delete($variableTable);
            $output->writeln(sprintf(
                '<info>Deleted %d row(s) from variable.</info>',
                $deletedVars
            ));

            $output->writeln('<info>✓ All custom variables deleted. Ready for fresh import.</info>');

        } catch (\Exception $e) {
            $output->writeln('<error>Delete failed: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }
}