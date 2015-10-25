<?php
/**
 * General Site Modules
 * 
 * @author Juan Pedro Gonzalez Gutierrez
 * @copyright Copyright (c) 2015 Juan Pedro Gonzalez Gutierrez
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html GPL v3
 */
namespace Site\Config;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Sql;

class Config
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * Cached configuration data.
     * 
     * @var array
     */
    protected $data = array();

    /**
     * @var string
     */
    protected $table;

    /**
     * Constructor.
     * 
     * @param string           $table
     * @param AdapterInterface $adapter
     */
    public function __construct($table, AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->table   = $table;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * isset() overloading
     *
     * @param  string $name
     * @return bool
     */
    public function __isset($name)
    {
        if (!array_key_exists($key, $this->data)) {
            $this->get($key);
        }

        return isset($this->data[$key]);
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return mixed
     */
    public function __set($value)
    {
        return $this->set($name, $value);
    }

    /**
     * unset() overloading
     *
     * @param  string $name
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function __unset($name)
    {
        // Delete from database
        $sql    = new Sql($this->adapter, $this->table);
        $delete = $sql->delete();

        $delete->where(array('key' => $key));

        $deleteState = $delete->getRawState();
        if ($deleteState['table'] != $this->table) {
            throw new Exception\RuntimeException('The table name of the provided Delete object must match that of the table');
        }

        // prepare and execute
        $statement = $sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();
        
        // Unset the cached value
        unset($this->data[$name]);
    }

    /**
     * Retrieve a value and return $default if there is no element set.
     *
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!array_key_exists($key, $this->data)) {
            // set the default value
            $this->data[$key] = $default;
        
            // Query database
            $sql    = new Sql($this->adapter, $this->table);
            $select = $sql->select();

            $select->where(array('key' => $key));

            $selectState = $select->getRawState();
            if ($selectState['table'] != $this->table && (is_array($selectState['table']) && end($selectState['table']) != $this->table)) {
                throw new Exception\RuntimeException('The table name of the provided select object must match that of the table');
            }

            // prepare and execute
            $statement = $sql->prepareStatementForSqlObject($select);
            $result    = $statement->execute();

            if ($result instanceof ResultInterface && $result->isQueryResult() && $result->getAffectedRows()) {
                $result = $result->current();
                $result = $result['value'];
                if ($result === 'b:0') {
                    $this->data[$key] = false;
                } else {
                    $data = @unserialize($result);
                    if ($data !== false) {
                        $this->data[$key] = $data;
                    } else {
                        $this->data[$key] = $result;
                    }
                }
            }
        }

        // return the value
        return $this->data[$key];
    }

    /**
     * 
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        if (array_key_exists($key, $this->data)) {
            return true;
        }

        // query the database for non-cached configuration
        $sql    = new Sql($this->adapter, $this->table);
        $select = $sql->select();

        $select->where(array('key' => $key));

        $selectState = $select->getRawState();
        if ($selectState['table'] != $this->table && (is_array($selectState['table']) && end($selectState['table']) != $this->table)) {
            throw new Exception\RuntimeException('The table name of the provided select object must match that of the table');
        }

        // prepare and execute
        $statement = $sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        if ($result instanceof ResultInterface && $result->isQueryResult() && $result->getAffectedRows()) {
            $result = $result->current();
            $result = $result['value'];
            if ($result === 'b:0') {
                $this->data[$key] = false;
            } else {
                $data = @unserialize($result);
                if ($data !== false) {
                    $this->data[$key] = $data;
                } else {
                    $this->data[$key] = $result;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Set a value in the config.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     */
    public function set($key, $value)
    {
        // serialize data
        $serialized = serialize($value);

        // Insert or Update?
        $keyExists = $this->has($key); 
        
        // SQL query
        $sql = new Sql($this->adapter, $this->table);

        if (!$keyExists) {
            $select = $this->sql->insert();
            $select->values(array('key' => $key, 'value' => $serialized));
        } else {
            $select = $sql->update();
            $select->set(array('value' => $serialized));
            $select->where(array('key' => $key));
        }

        $selectState = $select->getRawState();
        if ($selectState['table'] != $this->table) {
            throw new Exception\RuntimeException(
                sprintf('The table name of the provided %s object must match that of the table',
                (($keyExists) ? 'Update' : 'Insert')
            ));
        }

        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();

        // Store cached value
        $this->data[$key] = $value;
        return $this;
    }
}