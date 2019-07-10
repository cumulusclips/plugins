<?php

class VideoMetaMapper extends MapperAbstract
{
     /**
     * @var string Library file type
     */
    const TYPE_LIBRARY = 'library';

    /**
     * Main source table
     */
    const TABLE = 'videos_meta';

    /**
     * Source table's key field
     */
    const KEY = 'meta_id';

    /**
     * Parent/meta table's key field
     */
    const PARENT_KEY = 'video_id';

    /**
     * Retrieves all the meta
     * @params int $id The id of the meta owner
     * @return mapped parent class
     */
    public function getAllMeta($id)
    {
        return $this->getMultipleByCustom(array(static::PARENT_KEY => $id));
    }

    /**
     * Maps the values from a meta record to the properties in a related data model
     * @param array $record The record from the parent/related table
     * @return Object Returns an instance of a data model populated with the record's data
     */
    protected function _map($record)
    {
	    $parent_key = static::PARENT_KEY;
	    include_once "VideoMeta.php";
	    $meta = new VideoMeta();
	    $meta->$parent_key = (int) $record[static::PARENT_KEY];
	    $meta->meta_id = $record['meta_id'];
	    $meta->meta_key = $record['meta_key'];
	    $meta->meta_value = $record['meta_value'];
	    return $meta;
    }

    /**
     * Creates or updates a meta entry in the database. New entry is created if no id is provided
     *
     * @param Meta $meta The meta being saved
     * @return int Returns the id of the saved meta entry
     */
    public function save($meta)
    {
	
	    $parent_key = static::PARENT_KEY;

        $db = Registry::get('db');
        if (!empty($meta->meta_id)) {
            // Update
            $query = 'UPDATE ' . DB_PREFIX . static::TABLE . ' SET ';
            $query .= static::PARENT_KEY . ' = :' . static::PARENT_KEY . ', meta_key = :meta_key, meta_value = :meta_value';
            $query .= ' WHERE meta_id = :meta_id';
            $bindParams = array(
                ':meta_id' => $meta->meta_id,
                ':' . static::PARENT_KEY => $meta->$parent_key,
                ':meta_key' => $meta->meta_key,
                ':meta_value' => $meta->meta_value
            );
        } else {
            // Create
            $query = 'INSERT INTO ' . DB_PREFIX . static::TABLE;
            $query .= ' (' . static::PARENT_KEY . ', meta_key, meta_value)';
            $query .= ' VALUES (:' . static::PARENT_KEY . ', :meta_key, :meta_value)';
            $bindParams = array(
                ':' . static::PARENT_KEY => $meta->$parent_key,
                ':meta_key' => $meta->meta_key,
                ':meta_value' => $meta->meta_value
            );
        }
        $db->query($query, $bindParams);
        $metaId = (!empty($meta->meta_id)) ? $meta->meta_id : $db->lastInsertId();
        return $metaId;
    }

}
