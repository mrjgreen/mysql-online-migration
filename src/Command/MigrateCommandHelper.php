<?php namespace MysqlMigrate\Command;
use MysqlMigrate\DbConnection;
use MysqlMigrate\Migrate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommandHelper
{

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var QuestionHelper
     */
    private $questioner;

    /**
     * @var DbConnection
     */
    private $sourceConnection;

    /**
     * @var DbConnection
     */
    private $destConnection;

    /**
     * @var ConsoleLogger
     */
    private $logger;

    public function __construct(DbConnection $sourceConnection, DbConnection $destConnection, OutputInterface $output, Questioner $questioner)
    {
        $this->output = $output;

        $this->questioner = $questioner;

        $this->destConnection = $destConnection;

        $this->sourceConnection = $sourceConnection;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(\SplFileInfo $file, array $tablesList, $cleanup, $isInteractive)
    {
        if($file->isFile() && !$cleanup)
        {
            $this->output->writeln("<error>File $file exists. Try running cleanup with option '-c'</error>");

            return 1;
        }

        $this->output->writeln("<comment>Using file: $file</comment>");

        $migrate = new Migrate($this->sourceConnection, $this->destConnection);

        $migrate->setLogger($this->logger ?: new ConsoleLogger($this->output));

        $this->printTablesList($tablesList);

        if($isInteractive)
        {
            if(!$this->questioner->confirm("Are you sure you wish to continue with the migration? "))
            {
                $this->output->writeln("<error>Exiting</error>");

                return 2;
            }
        }

        if($cleanup)
        {
            $this->output->writeln("<comment>Running cleanup</comment>");

            $this->cleanup($migrate, $tablesList, $file);

            $this->output->writeln("<info>Cleanup complete</info>");
        }
        else
        {
            $this->output->writeln("<comment>Running migration</comment>");

            $migrate->migrate($tablesList, $file);

            $this->output->writeln("<info>Migration complete</info>");
        }

        return 0;
    }

    private function printTablesList(array $tables)
    {
        $this->output->writeln("Migrating:");

        foreach($tables as $table)
        {
            $this->output->writeln("\t" . $table[0] . ' => ' . $table[1]);
        }
    }

    private function cleanup(Migrate $migrate, array $tablesList, \SplFileInfo $file)
    {
        if($file->isFile())
        {
            unlink($file);

            $this->output->writeln("<info>Removed temp file: $file</info>");
        }

        $migrate->cleanup($tablesList);

        $this->output->writeln("<info>Removed triggers and deltas tables</info>");
    }
}