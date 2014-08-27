<?php

namespace BathHacked;

use Doctrine\Common\Cache\Cache;
use Guzzle\Http\Client;

class Socrata {

    const ABSOLUTE_MAXIMUM_CHUNK_SIZE = 1000;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var null|string
     */
    protected $appToken;

    /**
     * @var null|string
     */
    protected $username;

    /**
     * @var null|string
     */
    protected $password;

    /**
     * @var int
     */
    protected $maximumChunkSize = self::ABSOLUTE_MAXIMUM_CHUNK_SIZE;

    /**
     * @var \Guzzle\Http\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $path = null;

    /**
     * @var bool
     */
    protected $readOnly = false;


    /**
     * Build a Socrata client
     *
     * @param string $baseUrl The base url of your data store
     * @param string $appToken Your app token
     * @param string $username Your username
     * @param string $password Your password
     */
    public function __construct($baseUrl, $appToken = null, $username = null, $password = null)
    {
        $this->baseUrl = $baseUrl;
        $this->appToken = $appToken;
        $this->username = $username;
        $this->password = $password;

        $this->client = new Client($baseUrl);
    }

    /**
     * Helper to build headers
     *
     * @param array $params Query parameters
     * @return array
     */
    protected function getRequestDefaults($params)
    {
        $defaults = array(
            'query' => $params,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            )
        );

        if(!empty($this->username) && !empty($this->password))
        {
            $defaults['auth'] = array($this->username, $this->password);
        }

        if(!empty($this->appToken))
        {
            $defaults['headers']['X-App-Token'] = $this->appToken;
        }

        return $defaults;
    }

    /**
     * Make a GET request
     *
     * @param string $path Request path
     * @param array $params
     * @return mixed
     */
    public function get($path, $params = array())
    {
        $baseClientParams = $this->getRequestDefaults($params);

        $request = $this->client->get($path, array(), $baseClientParams);

        $response = $request->send()->getBody()->__toString();

        return json_decode($response, true);
    }

    /**
     * Make a POST request
     *
     * @param string $path Request path
     * @param mixed $payload
     * @param array $params
     * @return mixed
     */
    public function post($path, $payload, $params = array())
    {
        if($this->readOnly) throw new SocrataException('Cannot post to read-only connection');

        $baseClientParams = $this->getRequestDefaults($params);

        $request = $this->client->post($path, array(), json_encode($payload), $baseClientParams);

        $response = $request->send()->getBody()->__toString();

        return json_decode($response, true);
    }

    /**
     * Make a PUT request
     *
     * @param string $path Request path
     * @param mixed $payload
     * @param array $params
     * @return mixed
     */
    public function put($path, $payload, $params = array())
    {
        if($this->readOnly) throw new SocrataException('Cannot put to read-only connection');

        $baseClientParams = $this->getRequestDefaults($params);

        $request = $this->client->put($path, array(), json_encode($payload), $baseClientParams);

        $response = $request->send()->getBody()->__toString();

        return json_decode($response, true);
    }

    /**
     * Make a DELETE request
     *
     * @param string $path Request path
     * @param array $params
     * @return mixed
     */
    public function delete($path, $params = array())
    {
        if($this->readOnly) throw new SocrataException('Cannot delete on read-only connection');

        $baseClientParams = $this->getRequestDefaults($params);

        $request = $this->client->delete($path, array(), null, $baseClientParams);

        $response = $request->send()->getBody()->__toString();

        return json_decode($response, true);
    }

    /**
     * Get the maximum chunk size that Socrata will allow for GET requests
     *
     * @return int
     */
    public function getMaximumChunkSize()
    {
        return $this->maximumChunkSize;
    }

    /**
     * Set the maximum chunk size that Socrata will allow for GET requests
     *
     * @param int $size
     */
    public function setMaximumChunkSize($size)
    {
        if($size > self::ABSOLUTE_MAXIMUM_CHUNK_SIZE)
        {
            throw new SocrataException('Maximum chunk size cannot exceeed ' . self::ABSOLUTE_MAXIMUM_CHUNK_SIZE);
        }

        $this->maximumChunkSize = $size;
    }

    /**
     * Get the internal ID label
     *
     * @return string
     */
    public function getInternalId()
    {
        return ':id';
    }

    /**
     * Set connection to be read-only i.e. prevent put & post operations
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
     * Get a hash for this connection
     *
     * @return string
     */
    public function getConnectionHash()
    {
        return sha1($this->baseUrl);
    }

    /**
     * Create a Resource
     *
     * @param string $resource
     * @return Resource
     */
    public function resource($resource)
    {
        return new Resource($this, '/resource/' . $resource . '.json');
    }

    /**
     * Get the base path used for retrieving metadata
     *
     * @return string
     */
    public function getMetadataBasePath()
    {
        return '/views';
    }

    /**
     * Create a Metadata retriever object for this connection
     *
     * @return MetadataStore
     */
    public function metadata()
    {
        return new MetadataStore($this);
    }
}