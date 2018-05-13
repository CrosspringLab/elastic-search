<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.0.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\ElasticSearch\Datasource;

use Cake\Database\Log\LoggedQuery;
use Cake\Datasource\ConnectionInterface;
use Cake\ElasticSearch\Datasource\Log\QueryLoggerAdapter;
use Cake\Log\Log;
use Elastica\Client as ElasticaClient;
use Elastica\Log as ElasticaLog;
use Elastica\Request;
use Psr\Log\NullLogger;

class Connection implements ConnectionInterface
{
    /**
     * Whether or not query logging is enabled.
     *
     * @var bool
     */
    protected $logQueries = false;

    /**
     * The connection name in the connection manager.
     *
     * @var string
     */
    protected $configName = '';

    /**
     * Elastica client instance
     *
     * @var \Elastica\Client;
     */
    protected $_client;

    /**
     * Logger object instance.
     *
     * @var \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * NullLooger object instance
     *
     * @var \Psr\Log\NullLogger
     */
    protected $_nullLogger;

    /**
     * Constructor.
     *
     * @param array $config config options
     * @param callable $callback Callback function which can be used to be notified
     * about errors (for example connection down)
     */
    public function __construct(array $config = [], $callback = null)
    {
        if (isset($config['name'])) {
            $this->configName = $config['name'];
        }
        if (isset($config['log'])) {
            $this->logQueries((bool)$config['log']);
        }

        $this->_client = new ElasticaClient($config, $callback, $this->getLogger());
    }

    /**
     * Pass remaining methods to the elastica client (if they exist)
     * And set the current logger based on current logQueries value
     *
     * @param string $name Method name
     * @param array $attributes Method attributes
     * @return mixed
     */
    public function __call($name, $attributes)
    {
        if (method_exists($this->_client, $name)) {
            $this->_client->setLogger($this->getLogger());

            return call_user_func_array([$this->_client, $name], $attributes);
        }
    }

    /**
     * Returns a SchemaCollection stub until we can add more
     * abstract API's in Connection.
     *
     * @return \Cake\ElasticSearch\Datasource\SchemaCollection
     */
    public function getSchemaCollection()
    {
        return new SchemaCollection($this);
    }

    /**
     * {@inheritDoc}
     */
    public function configName()
    {
        return $this->configName;
    }

    /**
     * {@inheritDoc}
     */
    public function enabled()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function disableForeignKeys()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function enableForeignKeys()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->logQueries;
        }

        $this->logQueries = $enable;
    }

    /**
     * {@inheritDoc}
     */
    public function transactional(callable $callable)
    {
        return $callable($this);
    }

    /**
     * {@inheritDoc}
     *
     * Elasticsearch does not deal with the concept of foreign key constraints
     * This method just triggers the $callback argument.
     */
    public function disableConstraints(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Get the config data for this connection.
     *
     * @return array
     */
    public function config()
    {
        return $this->_client->getConfig();
    }

    /**
     * Sets a logger
     *
     * @param \Cake\Database\Log\QueryLogger|\Psr\Log\LoggerInterface $logger Logger instance
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;

        if ($this->_logger instanceof \Cake\Database\Log\QueryLogger) {
            $this->_logger = new QueryLoggerAdapter($this->_logger);
        }

        return $this;
    }

    /**
     * Get the logger object
     *
     * @return \Cake\Database\Log\QueryLogger logger instance
     */
    public function getLogger()
    {
        if (!$this->logQueries()) {
            return $this->getNullLogger();
        }

        if ($this->_logger === null) {
            $this->_logger = Log::engine('elasticsearch') ?: new ElasticaLog();
        }

        return $this->_logger;
    }

    /**
     * Return instance of the NullLogger
     *
     * @return \Psr\Log\NullLogger
     */
    public function getNullLogger()
    {
        if (!$this->_nullLogger) {
            $this->_nullLogger = new NullLogger;
        }

        return $this->_nullLogger;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use getLogger() and setLogger() instead.
     */
    public function logger($instance = null)
    {
        deprecationWarning(
            'Connection::logger() is deprecated. ' .
            'Use Connection::setLogger()/getLogger() instead.'
        );

        if ($instance === null) {
            return $this->getLogger();
        }

        $this->setLogger($instance);
    }
}
