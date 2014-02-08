<?php

namespace PicoDb;

class Database
{
    private static $instances = array();
    private $logs = array();
    private $pdo;


    public function __construct(array $settings)
    {
        if (! isset($settings['driver'])) {
            throw new \LogicException('You must define a database driver.');
        }

        switch ($settings['driver']) {

            case 'sqlite':
                require_once __DIR__.'/Drivers/Sqlite.php';
                $this->pdo = new Sqlite($settings['filename']);
                break;

            default:
                throw new \LogicException('This database driver is not supported.');
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }


    public static function bootstrap($name, callable $callback)
    {
        self::$instances[$name] = $callback;
    }


    public static function get($name)
    {
        if (! isset(self::$instances[$name])) {
            throw new \LogicException('No database instance created with that name.');
        }

        if (is_callable(self::$instances[$name])) {
            self::$instances[$name] = call_user_func(self::$instances[$name]);
        }

        return self::$instances[$name];
    }


    public function setLogMessage($message)
    {
        $this->logs[] = $message;
    }


    public function getLogMessages()
    {
        return $this->logs;
    }


    public function getConnection()
    {
        return $this->pdo;
    }


    public function escapeIdentifier($value)
    {
        return $this->pdo->escapeIdentifier($value);
    }


    public function execute($sql, array $values = array())
    {
        try {

            $this->setLogMessage($sql);
            $this->setLogMessage(implode(', ', $values));

            $rq = $this->pdo->prepare($sql);
            $rq->execute($values);

            return $rq;
        }
        catch (\PDOException $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollback();
            $this->setLogMessage($e->getMessage());
            return false;
        }
    }


    public function startTransaction()
    {
        $this->pdo->beginTransaction();
    }


    public function closeTransaction()
    {
        $this->pdo->commit();
    }


    public function cancelTransaction()
    {
        $this->pdo->rollback();
    }


    public function table($table_name)
    {
        require_once __DIR__.'/Table.php';
        return new Table($this, $table_name);
    }


    public function schema()
    {
        require_once __DIR__.'/Schema.php';
        return new Schema($this);
    }
}