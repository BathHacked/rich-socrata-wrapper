<?php


namespace BathHacked;


class MetadataStore {


    /**
     * @var Socrata
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param Socrata $socrata
     */
    public function __construct(Socrata $socrata)
    {
        $this->connection = $socrata;
    }

    /**
     * Get all metadata for the store
     *
     * @return array
     */
    public function all()
    {
        // Get all of the metadata from the connection

        $basePath = $this->connection->getMetadataBasePath();

        $metasRaw = $this->connection->get($basePath);

        // Wrap the raw metadata in an object

        $metas = [];

        foreach($metasRaw as $mr)
        {
            $meta = new Metadata($mr);

            $metas[$meta->getResourceId()] = $meta;

        }

        return $metas;
    }

    /**
     * Get a resource by id
     *
     * @param string $resourceId 4+4 socrata resource id
     * @return Metadata|mixed
     */
    public function resource($resourceId)
    {
        // Get the metadata for this resource from the connection

        $basePath = $this->connection->getMetadataBasePath();

        $metaRaw = $this->connection->get($basePath . '/' . $resourceId . '.json');

        // Wrap the raw metadata in an object

        $meta = new Metadata($metaRaw);

        return $meta;
    }

    /**
     * Return all metadata objects which have $search somewhere in one of their values
     *
     * @param string $search
     * @param array|null $metas an array of metadata objects to search or get all from connection if null
     * @return array
     */
    public function search($search, $metas = null)
    {
        if(!$metas)
        {
            $metas = $this->getAll();
        }

        $matched = [];

        /**
         * @var Metadata $m
         */
        foreach($metas as $m)
        {
            if($m->contains($search))
            {
                $matched[] = $m;
            }
        }

        return $matched;
    }
}