<?php


namespace BathHacked;


class Metadata {

    /**
     * The raw array of metadata retrieved from the store
     *
     * @var array
     */
    protected $raw;

    /**
     * Constructor
     *
     * @param array $raw The raw array of metadata retrieved from the store
     */
    public function __construct($raw)
    {
        $this->raw = $raw;
    }

    /**
     * Rebuild the object after var_export
     *
     * @param $a
     * @return Metadata
     */
    public static function __set_state($a)
    {
        return new Metadata($a['raw']);
    }

    /**
     * A nicer string representation
     *
     * @return string
     */
    public function __toString()
    {
        return "[{$this->id}] {$this->name}";
    }

    /**
     * Magic accessor, allowing us to access any top level field from the metadata
     *
     * @param string $field Top level key
     * @return mixed
     */
    public function __get($field)
    {
        return isset($this->raw[$field]) ? $this->raw[$field] : null;
    }

    /**
     * Spit out the raw metadata as an array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->raw;
    }

    /**
     * Json encode the metadata
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->raw);
    }

    /**
     * Get the id for the resource the metadata applies to
     *
     * @return string
     */
    public function getResourceId()
    {
        return $this->id;
    }

    /**
     * Get resource name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get resource description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get resource display type
     *
     * @return string
     */
    public function getDisplayType()
    {
        return $this->displayType;
    }

    /**
     * Get resource column info
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get resource category
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Get resource tags info
     *
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Get info about a particular column based on the column's numeric id
     *
     * @param int $id The numeric id of the column
     * @return mixed|null
     */
    public function getColumnById($id){

        if(!$this->columns) return null;

        foreach($this->columns as $col)
        {
            if($id == $col['id']) return $col;
        }

        return null;
    }

    /**
     * Get the numeric id of the column used to identify each row
     *
     * @return int
     */
    public function getRowIdentifierColumnId()
    {
        return $this->rowIdentifierColumnId;
    }

    /**
     * Get info about the row identifier column
     *
     * @return array
     */
    public function getRowIdentifierColumn()
    {
        return $this->getColumnById($this->getRowIdentifierColumnId());
    }

    /**
     * Does any value in this metadata contain the search term
     *
     * @param string $search
     * @return bool
     */
    public function contains($search)
    {
        $match = false;

        array_walk_recursive($this->raw, function($value, $key) use($search, &$match) {


            if(stristr($value, $search))
            {
                $match = true;
            }
        });

        return $match;
    }
}