<?php namespace MysqlMigrate\Command;
use MysqlMigrate\DbConnection;
use MysqlMigrate\Migrate;
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
            ->setDescription('Migrates database tables from one server to another.')
            ->addArgument('source', InputArgument::REQUIRED, 'The source DSN: host OR user:password@host')
            ->addArgument('destination', InputArgument::REQUIRED, 'The destination DSN: host OR user:password@host')
            ->addArgument('database.source', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The source databases to run against')
            ->addOption('database.destination', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The destination database to send to if different to source. Count should match source')
            ->addOption('tables','t', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The tables to run against. Accepts a regex expression')
            ->addOption('password','p', InputOption::VALUE_REQUIRED, 'The password for the specified user')
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

        $optionsParser = new PdoOptionsParser($input->getOption('user'), $input->getOption('password'));

        $sourceOps = $optionsParser->parsePdoOptions($input->getArgument('source'));

        $destOps = $optionsParser->parsePdoOptions($input->getArgument('destination'));

        $source = DbConnection::make("mysql:host={$sourceOps['host']}", $sourceOps['username'], $sourceOps['password']);

        $dest = DbConnection::make("mysql:host={$destOps['host']}", $destOps['username'], $destOps['password']);

        $migrate = new Migrate($source, $dest);

        $file = new \SplFileInfo('/tmp/migrate_file.dat');

        if($this->input->getOption('cleanup'))
        {
            $file->isFile() and unlink($file);

            $migrate->cleanup($this->getTableList($source));
        }
        else
        {
            if($file->isFile())
            {
                throw new \Exception('File ' . $file->getBasename() . ' exists. Try running cleanup with option "-c"');
            }

            $migrate->migrate($this->getTableList($source), $file);
        }
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