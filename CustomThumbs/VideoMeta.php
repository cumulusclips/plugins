<?php
namespace CustomThumbs;

use \Model;

class VideoMeta extends Model
{
    /**
     * @var int
     */
    public $mapper_id;

    /**
     * @var int
     */
    public $video_id;

    /**
     * @var string
     */
    public $meta_key;

    /**
     * @var string
     */
    public $meta_value;
}
