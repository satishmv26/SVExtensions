<?php
// app/code/SVExtensions/DynamicLabel/Console/Command/ImportCustomVariablesCommand.php

declare (strict_types = 1);

namespace SVExtensions\DynamicLabel\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Variable\Model\ResourceModel\Variable as VariableResource;
use Magento\Variable\Model\ResourceModel\Variable\CollectionFactory;
use Magento\Variable\Model\VariableFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCustomVariablesCommand extends Command
{
    private const COMMAND_NAME   = 'svextensions:dynamiclabel:import-variables';
    private const OPTION_FILE    = 'file';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_STORE   = 'store-id';
    private const OPTION_GLOBAL  = 'global';

    public function __construct(
        private readonly VariableFactory $variableFactory,
        private readonly VariableResource $variableResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Import Magento Custom Variables with store-scope support')
            ->addOption(self::OPTION_FILE, 'f', InputOption::VALUE_REQUIRED, 'Path to CSV or JSON file')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Preview without saving')
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_OPTIONAL, 'Force store_id (overrides CSV column)', null)
            ->addOption(self::OPTION_GLOBAL, 'g', InputOption::VALUE_NONE, 'Also save values to global scope (store_id=0)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // php bin/magento svextensions:dynamiclabel:import-variables --file=var/import/variables.csv --store-id=3 --global
        // code,name,html_value,plain_value,store_id - csv format

        // If --file not provided, use module's bundled CSV
        $filePath = $input->getOption(self::OPTION_FILE) ?: $this->getDefaultFilePath();
        $isDryRun     = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $forceStoreId = $input->getOption(self::OPTION_STORE);
        $saveGlobal   = (bool) $input->getOption(self::OPTION_GLOBAL); // ← resolve here

        if (! $filePath || ! file_exists($filePath)) {
            $output->writeln('<error>File not found: ' . $filePath . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $validStoreIds = $this->getValidStoreIds($output);
        $rows          = $this->parseFile($filePath, $output);

        if (empty($rows)) {
            $output->writeln('<comment>No rows parsed from file.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        if ($isDryRun) {
            $output->writeln('<info>[DRY RUN] No DB writes will occur.</info>');
        }

        $table = new Table($output);
        $table->setHeaders(['Code', 'Name', 'Store ID', 'HTML Value', 'Plain Value', 'Action']);

        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            if (empty($row['code']) || empty($row['name'])) {
                $output->writeln('<comment>Skipping row — missing code or name.</comment>');
                $skipped++;
                continue;
            }

            $storeId = $forceStoreId !== null
                ? (int) $forceStoreId
                : (isset($row['store_id']) && $row['store_id'] !== ''
                    ? (int) $row['store_id']
                    : 0);

            if (! in_array($storeId, $validStoreIds, true)) {
                $output->writeln(sprintf(
                    '<error>store_id=%d not valid for code=%s — defaulting to 0.</error>',
                    $storeId,
                    $row['code']
                ));
                $storeId = 0;
            }

            $action = $this->upsertVariable($row, $storeId, $isDryRun, $saveGlobal, $output);

            match ($action) {
                'created' => $created++,
                'updated' => $updated++,
                default   => $skipped++,
            };

            $table->addRow([
                $row['code'],
                $row['name'],
                $storeId === 0 ? 'Global (0)' : $storeId,
                substr($row['html_value'] ?? '', 0, 40),
                substr($row['plain_value'] ?? '', 0, 40),
                strtoupper($action),
            ]);
        }

        $table->render();
        $output->writeln(sprintf(
            '<info>Done — Created: %d | Updated: %d | Skipped: %d</info>',
            $created, $updated, $skipped
        ));

        return Cli::RETURN_SUCCESS;
    }

    protected function upsertVariable(
        array $data,
        int $storeId,
        bool $isDryRun,
        bool $saveGlobal,
        OutputInterface $output
    ): string {
        $code = trim($data['code']);

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('code', $code);
        $existing = $collection->getFirstItem();

        /** @var \Magento\Variable\Model\Variable $variable */
        $variable = $existing->getId()
            ? $existing
            : $this->variableFactory->create();

        $isNew = ! $existing->getId();

        $variable->setCode($code)->setName($data['name']);

        // ----------------------------------------------------------------
        // --global flag OR store_id=0 → write values via variableResource
        // No flag + store_id > 0     → parent record only, no global value
        // ----------------------------------------------------------------
        $writeGlobal = ($storeId === 0 || $saveGlobal);

        if ($writeGlobal) {
            $variable->setHtmlValue($data['html_value'] ?? '')
                ->setPlainValue($data['plain_value'] ?? '');
        } else {
            // store_id > 0 and no --global flag
            // New variable: set empty so resource model doesn't error
            // Existing variable: don't touch — preserve current global value
            if ($isNew) {
                $variable->setHtmlValue('')->setPlainValue('');
            }
        }

        if (! $isDryRun) {
            try {
                $this->variableResource->save($variable);
            } catch (\Exception $e) {
                $output->writeln('<error>Save failed [' . $code . ']: ' . $e->getMessage() . '</error>');
                return 'skipped';
            }

            if ($storeId > 0) {
                $variableId = (int) $variable->getId();

                if (! $variableId) {
                    $output->writeln('<error>variable_id missing after save for code=' . $code . '</error>');
                    return 'skipped';
                }

                $this->saveStoreValue(
                    $variableId,
                    $storeId,
                    $data['html_value'] ?? '',
                    $data['plain_value'] ?? '',
                    $output
                );
            }
        }

        return $isNew ? 'created' : 'updated';
    }

    private function saveStoreValue(
        int $variableId,
        int $storeId,
        string $htmlValue,
        string $plainValue,
        OutputInterface $output
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('variable_value');

        try {
            $connection->insertOnDuplicate(
                $table,
                [
                    'variable_id' => $variableId,
                    'store_id'    => $storeId,
                    'plain_value' => $plainValue,
                    'html_value'  => $htmlValue,
                ],
                ['plain_value', 'html_value']
            );

            $output->writeln(sprintf(
                '<info>  → Store value saved [variable_id=%d, store_id=%d]</info>',
                $variableId,
                $storeId
            ));

        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>Store value failed [variable_id=%d, store_id=%d]: %s</error>',
                $variableId,
                $storeId,
                $e->getMessage()
            ));
        }
    }

    // ------------------------------------------------------------------ //
    //  STORE VALIDATION
    // ------------------------------------------------------------------ //

    private function getValidStoreIds(OutputInterface $output): array
    {
        $ids = [0];
        try {
            foreach ($this->storeManager->getStores() as $store) {
                $ids[] = (int) $store->getId();
            }
        } catch (\Exception $e) {
            $output->writeln('<comment>Could not load stores: ' . $e->getMessage() . '</comment>');
        }
        return $ids;
    }

    // ------------------------------------------------------------------ //
    //  FILE PARSERS
    // ------------------------------------------------------------------ //

    private function parseFile(string $filePath, OutputInterface $output): array
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'json'  => $this->parseJson($filePath, $output),
            'csv'   => $this->parseCsv($filePath, $output),
            default => []
        };
    }

    private function parseJson(string $filePath, OutputInterface $output): array
    {
        $data = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Invalid JSON: ' . json_last_error_msg() . '</error>');
            return [];
        }
        return is_array($data) ? $data : [];
    }

    private function parseCsv(string $filePath, OutputInterface $output): array
    {
        $rows   = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            $output->writeln('<error>Cannot open file: ' . $filePath . '</error>');
            return [];
        }

        // Strip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (! $headers) {
            $output->writeln('<error>CSV headers missing.</error>');
            fclose($handle);
            return [];
        }

        $headers = array_map(
            fn($h) => preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($h)),
            $headers
        );

        $output->writeln('<comment>Headers: ' . implode(' | ', $headers) . '</comment>');

        $line = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;

            if (count($row) === 1 && trim($row[0]) === '') {
                continue;
            }

            if (count($row) !== count($headers)) {
                $output->writeln("<comment>Line $line skipped — column mismatch.</comment>");
                continue;
            }

            $mapped = array_combine($headers, $row);
            if ($mapped !== false) {
                $rows[] = array_map('trim', $mapped);
            }
        }

        fclose($handle);
        $output->writeln('<info>Parsed ' . count($rows) . ' row(s).</info>');
        return $rows;
    }

    private function getDefaultFilePath(): string
    {
        // Resolves to: app/code/SVExtensions/DynamicLabel/files/variables.csv
        $modulePath = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'SVExtensions_DynamicLabel'
        );

        return $modulePath . '/files/variables.csv';
    }

    private function getDefaultFilePathByOldway(): string
    {
        // __FILE__ = .../app/code/SVExtensions/DynamicLabel/Console/Command/ImportCustomVariablesCommand.php
        // Go up 3 levels: Command → Console → DynamicLabel (module root)
        $moduleRoot = dirname(__FILE__, 3);

        return $moduleRoot . '/files/variables.csv';
    }
}
