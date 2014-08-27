<?php


namespace BathHacked;


class Resource {

    /**
     * Number of items at which all() will bailout
     */
    const BAILOUT = 100000;

    /**
     * Value to indicate all() should not bailout
     */
    const FETCH_ALL = 0;

    /**
     * @var Socrata
     */
    protected $connection;

    /**
     * @var bool
     */
    protected $readOnly = false;

    /**
     * @var null|string
     */
    protected $path = null;

    /**
     * @var array
     */
    protected $selects = array();

    /**
     * @var array
     */
    protected $wheres = array();

    /**
     * @var array
     */
    protected $orders = array();

    /**
     * @var array
     */
    protected $groups = array();

    /**
     * @var string|null
     */
    protected $textSearch = null;

    /**
     * @var int|null
     */
    protected $limit = null;

    /**
     * @var int|null
     */
    protected $offset = null;

    /**
     * @var int
     */
    protected $chunkSize = 1000;

    /**
     * Constructor
     *
     * @param Socrata $connection
     * @param string $path Path to endpoint URL without base protocol & domain e.g. /resource/xyz123.json
     */
    public function __construct(Socrata $connection, $path)
    {
        $this->connection = $connection;

        $this->path = $path;

        $this->chunkSize = $connection->getMaximumChunkSize();
    }

    /**
     * Set endpoint to be read-only i.e. prevent put & post operations
     *
     * @param bool $isReadOnly Defaults to true provide false to turn off
     * @return $this
     */
    public function readOnly($isReadOnly = true)
    {
        $this->readOnly = $isReadOnly;

        return $this;
    }

    /**
     * Reset the query
     *
     * @return $this
     */
    public function resetQuery()
    {
        $this->selects = array();
        $this->wheres = array();
        $this->orders = array();
        $this->groups = array();
        $this->textSearch = null;
        $this->limit = null;
        $this->offset = null;

        return $this;
    }

    /**
     * Specify select clauses for query
     *
     * e.g. ["col1", "MAX(col2) as 'max'"]
     *
     * @param array $fieldSpecs Field specifications
     * @return $this
     */
    public function select(array $fieldSpecs)
    {
        $this->selects = $fieldSpecs;

        return $this;
    }

    /**
     * Add a select clause to the query
     *
     * @param string $fieldSpec Field specification
     * @return $this
     */
    public function addSelect($fieldSpec)
    {
        $this->selects[] = $fieldSpec;

        return $this;
    }

    /**
     * Add a where clause to the query
     *
     * NB. All clauses are ANDed together
     *
     * Examples :-
     * "name = 'foobar'"
     * "(x > '2' OR y < '3')"
     * "(foo IS NOT NULL)"
     *
     * @param $clause string
     * @return $this
     */
    public function whereRaw($clause)
    {
        $this->wheres[] = $clause;

        return $this;
    }

    /**
     * Helper to wrap value in quotes
     *
     * @param $field
     * @param $operator
     * @param $value
     * @return $this
     */
    public function where($field, $operator, $value)
    {
        $this->whereRaw($field . $operator . "'" . $value . "'");

        return $this;
    }

    /**
     * Helper for simple equality
     *
     * @param $field
     * @param $value
     * @return $this
     */
    public function whereEquals($field, $value)
    {
        $this->where($field, '=', $value);

        return $this;
    }

    /**
     * Add an order by clause to the query
     *
     * @param string $field Field to order on
     * @param string $direction Direction to order ASC|DESC
     * @return $this
     */
    public function orderBy($field, $direction = 'ASC')
    {
        $this->orders[] = $field . ' ' . $direction;

        return $this;
    }

    /**
     * @param string $field Field to group by
     * @return $this
     */
    public function groupBy($field)
    {
        $this->groups[] = $field;

        return $this;
    }

    /**
     * Add a full text search to the query
     *
     * N.B. Only one search can be added per query
     *
     * @param string $query
     * @return $this
     * @throws ResourceException
     */
    public function textSearch($query)
    {
        if(!is_null($this->textSearch))
        {
            throw new ResourceException("Only one text search can be defined per query");
        }

        $this->textSearch = $query;

        return $this;
    }

    /**
     * Add a limit to the query
     *
     * N.B. The limit cannot exceed the maximum chunk size defined by the connection
     *
     * @param int $limit
     * @return $this
     * @throws ResourceException
     */
    public function limit($limit)
    {
        $chunkSize = $this->connection->getMaximumChunkSize();

        if($limit > $chunkSize)
        {
            throw new ResourceException("Limit exceeds connection maximum chunk size of {$chunkSize}");
        }

        $this->limit = $limit;

        return $this;
    }

    /**
     * Add an offset to the query
     *
     * @param int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Build params to be passed to connection request from this instance's query clauses
     *
     * @return array
     */
    public function toParams()
    {
        $params = array();

        if(!empty($this->selects)) $params['$select'] = implode(',', $this->selects);
        if(!empty($this->wheres)) $params['$where'] = implode(' AND ', $this->wheres);
        if(!empty($this->orders)) $params['$order'] = implode(',', $this->orders);
        if(!empty($this->groups)) $params['$group'] = implode(',', $this->groups);
        if(!empty($this->textSearch)) $params['$q'] = $this->textSearch;
        if(!empty($this->limit)) $params['$limit'] = $this->limit;
        if(!empty($this->offset)) $params['$offset'] = $this->offset;

        return $params;
    }

    /**
     * Get data from the connection based on query
     *
     * @return array
     */
    public function get()
    {
        $params = $this->toParams();

        return $this->connection->get($this->path, $params);
    }

    /**
     * Get all data matching query ignoring limit & offset
     *
     * @param int $bailout A sanity check to prevent retrieving a crazy number of rows. Set to self::FETCH_ALL to
     * @return array
     */
    public function all($bailout = self::BAILOUT)
    {
        $all = [];

        $fetched = 0;

        $this->iterateChunks(function($chunk) use(&$all, &$fetched, $bailout) {

            if($bailout != self::FETCH_ALL && $fetched >= $bailout) return false;

            $all = array_merge($chunk, $all);

            $fetched += count($chunk);
        });

        return $all;
    }

    /**
     * Get the chunk size
     *
     * @return int
     */
    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    /**
     * Set the chunk size
     *
     * @param $size
     * @throws ResourceException
     */
    public function setChunkSize($size)
    {
        $maximumChunkSize = $this->connection->getMaximumChunkSize();

        if($size > $maximumChunkSize)
        {
            throw new ResourceException('Chunk size cannot exceed ' . $maximumChunkSize);
        }

        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Repeatedly get data from connection which matches query, ignoring limit & offset
     * and pass result to callback
     *
     * Each chunk, current offset & limit are passed to callback. Callback should return false to stop iteration.
     *
     * @param \Closure $callback
     * @return $this;
     */
    public function iterateChunks(\Closure $callback)
    {
        $oldLimit = $this->limit;
        $oldOffset = $this->offset;

        // Limit our chunks to chunk size

        $chunkSize = $this->chunkSize;
        $this->limit = $chunkSize;
        $this->offset = 0;

        do
        {
            // Get a chunk

            $chunk = $this->get();

            // If the chunk contains any rows

            if(count($chunk) > 0)
            {
                // Invoke callback with the chunk, offset & limit

                $invokeResult = $callback($chunk, $this->offset, $this->limit);

                // Advance offset by maximum chunk size

                $this->offset += $chunkSize;
            }

        } while(count($chunk) > 0 && $invokeResult !== false);

        $this->limit = $oldLimit;
        $this->offset = $oldOffset;

        return $this;
    }

    /**
     * Iterate over all rows matching query, regardless of limit & offset and pass result to
     * callback
     *
     * Each item, current offset & limit are passed to callback

     * @param \Closure $callback
     * @return $this
     */
    public function iterateRows(\Closure $callback)
    {
        // Walk over each item in each chunk

        $this->iterateChunks(function($chunk) use($callback) {

            array_walk($chunk, $callback);

        });

        return $this;
    }

    /**
     * Upsert items
     *
     * $resultCallback is called with information about the bulk operation
     * @see http://dev.socrata.com/publishers/upsert.html#performing_your_upsert
     *
     * @param array $items Items to be upserted
     * @param \Closure $resultCallback
     * @return $this
     * @throws ResourceException
     */
    public function upsert($items, \Closure $resultCallback = null)
    {
        if($this->readOnly) throw new ResourceException('Cannot post to read-only endpoint');

        // Break input items into chunks

        $chunks = array_chunk($items, $this->chunkSize);

        // POST each chunk

        $sent = 0;

        foreach($chunks as $chunk)
        {
            $result = $this->connection->post($this->path, $chunk);

            $sent += count($chunk);

            if($resultCallback) $resultCallback($sent, $result);
        }

        return $this;
    }

    /**
     * Upsert items
     *
     * $resultCallback is called with information about the bulk operation
     * @see http://dev.socrata.com/publishers/replace.html
     *
     * @param array $items Items to be upserted
     * @param \Closure $resultCallback
     * @return $this
     * @throws ResourceException
     */
    public function replace($items, \Closure $resultCallback = null)
    {
        if($this->readOnly) throw new ResourceException('Cannot replace read-only endpoint');

        // Break input items into chunks

        $chunks = array_chunk($items, $this->chunkSize);

        // PUT each chunk

        $sent = 0;

        foreach($chunks as $chunk)
        {
            $result = $this->connection->put($this->path, $chunk);

            $sent += count($chunk);

            if($resultCallback) $resultCallback($sent, $result);
        }

        return $this;
    }


    /**
     * Delete items
     *
     * $resultCallback is called with information about the bulk operation
     * @see http://dev.socrata.com/publishers/replace.html
     *
     * @param \Closure $resultCallback
     * @return $this
     */
    public function delete(\Closure $resultCallback = null)
    {
        $oldSelects = $this->selects;

        if($this->readOnly) throw new ResourceException('Cannot delete from read-only endpoint');

        // We only need the internal id

        $internalId = $this->connection->getInternalId();

        $this->selects = [$internalId];

        // Build our closure & bind to $this

        $deleter = function($chunk) use($resultCallback) {

            foreach($chunk as $index => $item)
            {
                $chunk[$index][':deleted'] = true;
            }

            $this->upsert($chunk, $resultCallback);
        };

        /**
         * @var \Closure $deleter
         */

        $deleter->bindTo($this);

        // Iterate over chunks

        $this->iterateChunks($deleter);

        $this->selects = $oldSelects;

        return $this;
    }
}