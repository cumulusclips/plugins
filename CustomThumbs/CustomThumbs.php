<?php

class CustomThumbs extends PluginAbstract
{
	/**
	* @var string Name of plugin
	*/
	public $name = 'CustomThumbs';

	/**
	* @var string Description of plugin
	*/
	public $description = 'Choose your own thumbnail by uploading/attaching an image to a video.';

	/**
	* @var string Name of plugin author
	*/
	public $author = 'Justin Henry';

	/**
	* @var string URL to plugin's website
	*/
	public $url = 'https://uvm.edu/~jhenry/';

	/**
	* @var string Current version of plugin
	*/
	public $version = '0.0.1';
	
    /**
     * Performs install operations for plugin. Called when user clicks install
     * plugin in admin panel.
     *
     */
    public function install(){

        $db = Registry::get('db');
        if(!CustomThumbs::tableExists($db, 'video_meta')) {
            $video_query = "CREATE TABLE IF NOT EXISTS videos_meta (
                meta_id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                video_id bigint(20) NOT NULL,
                meta_key varchar(255) NOT NULL,
                meta_value longtext NOT NULL);";	

            $db->query($video_query);
        }
    }

    /**
     * Performs uninstall operations for plugin. Called when user clicks
     * uninstall plugin in admin panel and prior to files being removed.
     *
     */
    public function uninstall(){

        $db = Registry::get('db');

        $drop_video_meta = "DROP TABLE IF EXISTS videos_meta;";

        $db->query($drop_video_meta );
    }

	
	/**
	* Attaches plugin methods to hooks in code base
	*/
	public function load() {
		Plugin::attachEvent ( 'theme.head' , array( __CLASS__ , 'load_styles' ) );
        Plugin::attachEvent( 'videos.edit.attachment.list' , array( __CLASS__ , 'edit_custom_thumbnail' ) );
        Plugin::attachEvent( 'theme.thumbnail.url' , array( __CLASS__ , 'thumb_url' ) );
        Plugin::attachEvent( 'videos_edit.start' , array( __CLASS__ , 'set_custom_thumbnail' ) );
	}

	/**
	* Add CSS stylesheet to head
	* 
 	*/
	public static function load_styles() {
        $config = Registry::get('config'); 
        $css_url = $config->baseUrl . '/cc-content/plugins/CustomThumbs/style.css';
		echo '<link href="' . $css_url . '" rel="stylesheet">';
		
    }

	/**
	* Insert video thumbnail url into  player.
	*
	* @param int $video_id Id of video for which we are getting the thumb 
	* @return string $url link to the thumb
	*
 	*/
	public static function thumb_url($video_id) {

        // Get file id of the thumb (or false if not set)
		$video_meta = CustomThumbs::get_video_meta($video_id, 'thumbnail');
		//$custom_thumbnail = $video_meta->meta_value;

        // Get file url based on file id stored in meta
        if( $video_meta ){
            $fileMapper = new FileMapper();
            $file = $fileMapper->getById($video_meta->meta_value);

            $fileService = new FileService();
            $url = $fileService->getUrl($file);
        } else {
            $config = Registry::get('config');
            $videoMapper = new VideoMapper();
            $video = $videoMapper->getVideoById($video_id);
            $url = $config->thumbUrl . "/" . $video->filename . ".jpg";
        }

		echo $url;
	}

	/**
	* Display additional form elements in the attachment list 
	* to allow setting an image as the default thumbnail.
	*
	* @param int $file_id Id of file we are editing
	* @param int $video_id Id of the video this file is attached to 
	* @return string $link HTML form elements for thumbnail file settings.
	*
 	*/
	public static function edit_custom_thumbnail($file_id, $video_id) {
		$fileMapper = new FileMapper();
		$file = $fileMapper->getById($file_id);
		$form = "";
		//if it's a supported image file
		if(CustomThumbs::is_valid_thumbnail($file))
		{
            $fileService = new FileService();
            $image_url = $fileService->getURL($file);

            // If there is a thumbnail set for this video,
            $video_meta = CustomThumbs::get_video_meta($video_id, 'thumbnail');
            if( $video_meta ) {

                // and if this file is set to be the thumb for that video 
                if($file->fileId == $video_meta->meta_value)
                {
                    $checked = "checked";
                } else{
                    $checked = "";
                }
                $form = '<p class="set-thumbnail"><input type="radio" name="custom_thumbnail" value="' . $file->fileId . '" ' . $checked . '> <label class="control-label custom-thumbnail" for="custom_thumbnail">Make this the thumbnail and poster image for the video:</label></p>';
                $form .= '<p class="set-thumbnail"><img alt="Preview of uploaded image." src="' . $image_url . '"></p>';

                //$form .= '<p class="delete-image"><a class="remove" href=""><span class="pull-right">Delete File</span></a></p>';
            }
        }
		
		echo $form;	
	}
	
	/**
	* Set a default thumbnail image. 
	*
	*/
	public static function set_custom_thumbnail() {
		if(isset($_POST['custom_thumbnail'])){
			$file_id = $_POST['custom_thumbnail'];		
			$attachmentMapper = new AttachmentMapper();
			$attachment = $attachmentMapper->getByCustom(array("file_id" => $file_id));
			
			CustomThumbs::update_video_meta($attachment->videoId, 'thumbnail', $file_id);
		}
	}

	/**
	 * Save/Create video meta entries.
	 * 
	 * @param int $video_id Id of the video this meta belongs to 
	 * @param string $meta_key reference label for the meta item we are updating
	 * @param string $meta_value data entry being updated
	 * 
	 */
	public static function update_video_meta($video_id, $meta_key, $meta_value) {
		
		include_once "VideoMeta.php";
		$videoMeta = new \CustomThumbs\VideoMeta();

		// If there's meta for this file, we want the meta id
		$existing_meta = CustomThumbs::get_video_meta($video_id, $meta_key);
		if($existing_meta){
			$videoMeta->meta_id = $existing_meta->meta_id;
		}


		$videoMeta->video_id = $video_id;
		$videoMeta->meta_key = $meta_key;
		$videoMeta->meta_value = $meta_value;
		
        $videoMetaMapper = CustomThumbs::get_mapper_class();
		
		$videoMetaMapper->save($videoMeta);
		
	}
	
	/**
	* Clean up meta (i.e. video and file) entries when a subtitle is removed.
	* Compares submitted form data against meta entries in the DB. 
	* 
	*/
	public static function cleanup_deleted_meta() {
		$submittedAttachmentFileIds = array();

		// Get a list of attachments that were posted via the video edit form
		if (isset($_POST['attachment']) && is_array($_POST['attachment'])) {
			foreach ($_POST['attachment'] as $attachment) {
				if (!empty($attachment['file'])) {
					$submittedAttachmentFileIds[] = $attachment['file'];
				}
			}

			// Get all attachments in the DB for this video
			$video_id = $_GET['vid'];
			$existing_images = CustomThumbs::get_valid_thumbnails($video_id);

			foreach($existing_images as $image){
				
				// If the item in the DB is not included in the 
				// attachments submitted via the form, we delete it's meta records.
				if( !in_array($image->fileId, $submittedAttachmentFileIds) ){

					// And if the ID is listed as a default item for a video, clean that up too
					$video_meta = CustomThumbs::get_video_meta($video_id, 'thumbnail');
					if($image->fileId == $video_meta->meta_value){
                        $videoMetaMapper = CustomThumbs::get_mapper_class();
						$videoMetaMapper->delete($video_meta->meta_id);
					}
				}

			}
		}

	}
	

	/**
	 * Get a list of all attached thumbnail image files
	 * 
	 * @param int $video_id Id of the video we are querying image attachments for
	 * @return array $thumbs List of attachment objects that are valid thumbnail images 
	 */
	public static function get_valid_thumbnails($video_id) {

		$attachmentMapper = new AttachmentMapper();
		$attachments = $attachmentMapper->getMultipleByCustom(array("video_id" => $video_id));

		//for each attachment, if it's a valid item, put it on the stack
		$fileMapper = new FileMapper();
        $thumbs = array();
		foreach($attachments as $attachment){
			$file = $fileMapper->getById($attachment->fileId);
			
			if(CustomThumbs::is_valid_thumbnail($file)){
				$thumbs[] = $file;
			}
		}
		return $thumbs;
		
	}

	/**
	* Determine if the specified file is the right type of file
	*
	* @param File $file file object 
	*/
	public static function is_valid_thumbnail($file) {
		
		//TODO: move to plugin settings
		$valid_thumbs = array('jpg', 'gif', 'png', 'jpeg');
		
		return in_array($file->extension, $valid_thumbs);
		
	}

	/**
	 * Get video meta entry
	 * 
	 * @param int $video_id Id of the video this meta belongs to 
	 * @param string $meta_key reference label for the meta item to retrieve
	 * @return false if not found 
	 */
		public static function get_video_meta($video_id, $meta_key) {
            
			$videoMetaMapper = CustomThumbs::get_mapper_class();
			$meta = $videoMetaMapper->getByCustom( array('video_id' => $video_id, 'meta_key' => $meta_key) );	
			return $meta;
		}

	/**
	 * Check if a table exists in the current database.
	 *
	 * @param PDO $pdo PDO instance connected to a database.
	 * @param string $table Table to search for.
	 * @return bool TRUE if table exists, FALSE if no table found.
	 */
	public static function tableExists($pdo, $table) {

		// Try a select statement against the table
		// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
		try {
			$result = $pdo->basicQuery("SELECT 1 FROM $table LIMIT 1");
		} catch (Exception $e) {
			// We got an exception == table not found
			return FALSE;
		}

		// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
		return $result !== FALSE;
	}

	/**
	 * Get instance of a mapper class
	 *
	 * @return class namespaced instance of class
	 */
	public static function get_mapper_class() {

			include_once 'VideoMetaMapper.php';
			$videoMetaMapper = new \CustomThumbs\VideoMetaMapper();	
            return $videoMetaMapper;

    }

}
