<?php namespace MysqlMigrate\Command;
use MysqlMigrate\DbConnection;
use MysqlMigrate\Migrate;
use MysqlMigrate\TableName;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

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

    protected function question($question, $default = null)
    {
        $question = new Question($question, $default);

        return $helper = $this->getHelper('question')->ask($this->input, $this->output, $question);
    }

    protected function confirm($question)
    {
        $question = new ConfirmationQuestion($question, false);

        return $helper = $this->getHelper('question')->ask($this->input, $this->output, $question);
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

        $file = new \SplFileInfo($this->input->getOption('file'));

        if($file->isFile() && !$this->input->getOption('cleanup'))
        {
            throw new \Exception("File $file exists. Try running cleanup with option '-c'");
        }

        $output->writeln("<comment>Using file: $file</comment>");

        list($source, $dest) = $this->createConnections();

        $migrate = new Migrate($source, $dest);

        $migrate->setLogger(new ConsoleLogger($output));

        $tablesList = $this->getTableList($source);

        $this->printTablesList($tablesList);

        if($this->input->isInteractive())
        {
            if(!$this->confirm("Are you sure you wish to continue with the migration? "))
            {
                $this->output->writeln("<error>Exiting</error>");
                return 0;
            }
        }

        if($this->input->getOption('cleanup'))
        {
            $this->output->writeln("<comment>Running cleanup</comment>");

            $this->cleanup($migrate, $tablesList, $file);

            $output->writeln("<info>Cleanup complete</info>");
        }
        else
        {
            $output->writeln("<comment>Running migration</comment>");

            $migrate->migrate($tablesList, $file);

            $output->writeln("<info>Migration complete</info>");
        }
    }

    private function printTablesList(array $tables)
    {
        $this->output->writeln("Migrating:");

        foreach($tables as $table)
        {
            $this->output->writeln("\t" . $table[0] . ' => ' . $table[1]);
        }
    }

    private function createConnections()
    {
        $password = $this->input->getOption('password');

        if(!$password && $this->input->hasOption('password'))
        {
            while(!$password = $this->question("Enter your mysql connection password: "));
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

    private function cleanup(Migrate $migrate, array $tablesList, $file)
    {
        if($file->isFile())
        {
            unlink($file);

            $this->output->writeln("<info>Removed temp file: $file</info>");
        }

        $migrate->cleanup($tablesList);

        $this->output->writeln("<info>Removed triggers and deltas tables</info>");
    }

    private function getTableList(DbConnection $source)
    {
        $sourceDbs = $this->input->getArgument('database.source');

        $destDbs = $this->input->getOption('database.destination') ?: $sourceDbs;

        if(count($sourceDbs) !== count($destDbs))
        {
            throw new \InvalidArgumentException("Source database count must match target database count");
        }

        $tableFilter = $this->input->getOption('tables');

        $tablesAll = array();

        foreach($sourceDbs as $i => $db)
        {
            $tables = $source->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = ?", array($db))->fetchAll();

            foreach($tables as $t)
            {
                $t = $t['TABLE_NAME'];

                if($this->inFilterList($tableFilter, $t))
                {
                    $tablesAll[] = array(new TableName($db, $t), new TableName($destDbs[$i], $t));
                }
            }
        }

        return $tablesAll;
    }

    public function inFilterList(array $filter, $table)
    {
        foreach($filter as $f)
        {
            if(self::is($f, $table))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param  string  $pattern
     * @param  string  $value
     * @return bool
     */
    private static function is($pattern, $value)
    {
        if ($pattern == $value) return true;
        $pattern = preg_quote($pattern, '#');
        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace('\*', '.*', $pattern).'\z';
        return (bool) preg_match('#^'.$pattern.'#', $value);
    }
}