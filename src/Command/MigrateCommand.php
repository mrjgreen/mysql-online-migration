<?php namespace MysqlMigrate\Command;
use MysqlMigrate\DbConnection;
use MysqlMigrate\Helper\PdoOptionsParser;
use MysqlMigrate\Helper\TableLister;
use MysqlMigrate\TableName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var InputInterface
     */
    private $input;

    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrates database tables from one server to another')
            ->addArgument('source', InputArgument::REQUIRED, 'The source DSN: host OR user:password@host')
            ->addArgument('destination', InputArgument::REQUIRED, 'The destination DSN: host OR user:password@host')
            ->addArgument('database.source', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The source databases to run against')
            ->addOption('database.destination', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The destination database to send to if different to source. Count should match source')
            ->addOption('tables','t', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The tables to run against. Accepts a regex expression')
            ->addOption('file','f', InputOption::VALUE_REQUIRED, 'The temporary file to use', sys_get_temp_dir() . '/migrate.tmp.dat')
            ->addOption('password','p', InputOption::VALUE_OPTIONAL, 'The password for the specified user')
            ->addOption('user','u', InputOption::VALUE_REQUIRED, 'The name of the user to connect with')
            ->addOption('cleanup','c', InputOption::VALUE_NONE, 'Cleanup - remove all triggers and deltas')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        $questioner = new Questioner($input, $output, $this->getHelper('question'));

        $file = new \SplFileInfo($this->input->getOption('file'));

        list($source, $dest) = $this->createConnections($questioner);

        $migrateCommandHelper = new MigrateCommandHelper($source, $dest, $output, $questioner);

        if($this->input->isInteractive()) $migrateCommandHelper->disableInteraction();

        return $migrateCommandHelper->execute($file, $this->getTableList($source), $this->input->getOption('cleanup'));
    }

    /**
     * @param Questioner $questioner
     * @return array
     * @throws \Exception
     */
    private function createConnections(Questioner $questioner)
    {
        $password = $this->input->getOption('password');

        if(!$password && $this->input->hasOption('password'))
        {
            $password = $questioner->secret("Enter your mysql connection password: ");
        }

        $optionsParser = new PdoOptionsParser($this->input->getOption('user'), $password);

        $sourceOps = $optionsParser->parsePdoOptions($this->input->getArgument('source'));

        if(!file_exists($sourceOps['host']))
        {
            throw new \InvalidArgumentException("The source connection must be a local socket");
        }

        $destOps = $optionsParser->parsePdoOptions($this->input->getArgument('destination'));

        $source = DbConnection::make("mysql:unix_socket={$sourceOps['host']}", $sourceOps['username'], $sourceOps['password']);

        $this->output->writeln("<info>Created local connection using {$sourceOps['host']}</info>");

        $dest = DbConnection::make("mysql:host={$destOps['host']}", $destOps['username'], $destOps['password']);

        $this->output->writeln("<info>Created remote connection to {$destOps['host']}</info>");

        return array($source, $dest);
    }

    /**
     * @param DbConnection $source
     * @return array
     */
    private function getTableList(DbConnection $source)
    {
        $sourceDbs = $this->input->getArgument('database.source');

        $destDbs = $this->input->getOption('database.destination') ?: $sourceDbs;

        if(count($sourceDbs) !== count($destDbs))
        {
            throw new \InvalidArgumentException("Source database count must match target database count");
        }

        $tableFilters = $this->input->getOption('tables');

        $tableLister = new TableLister($source);

        $tablesAll = array();

        foreach($sourceDbs as $i => $db)
        {
            $filtered = $tableLister->filterTables($tableLister->getTableList($db), $tableFilters);

            foreach($filtered as $table)
            {
                $tablesAll[] = array(new TableName($db, $table), new TableName($destDbs[$i], $table));
            }
        }

        return $tablesAll;
    }
}