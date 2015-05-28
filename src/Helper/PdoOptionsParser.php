<?php namespace MysqlMigrate\Helper;

class PdoOptionsParser
{
    private $defaultUser = null;

    private $defaultPassword = null;

    public function __construct($defaultUser = null, $defaultPassword = null)
    {
        $this->defaultUser = $defaultUser;

        $this->defaultPassword = $defaultPassword;
    }

    public function parsePdoOptions($dsn)
    {
        $parts = explode('@', $dsn, 2);

        if(count($parts) < 2)
        {
            if(!$this->defaultUser)
            {
                throw new \Exception('Please provide a user in the dsn arguments or using the --user option');
            }

            array_unshift($parts, $this->defaultUser . ':' . $this->defaultPassword);
        }

        $usernameAndPassword = explode(':', $parts[0]);

        if(count($usernameAndPassword) !== 2)
        {
            throw new \Exception('Invalid DSN string for host ' . $parts[1] . '. Please provide the format user:pass@host');
        }

        return array(
            'host'      => $parts[1],
            'username'  => $usernameAndPassword[0],
            'password'  => $usernameAndPassword[1],
        );
    }
}