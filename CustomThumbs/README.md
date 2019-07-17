# Custom Thumbnails for CumulusClips

Change the thumbnail/poster image for a video uploaded to the CumulusClips video CMS.

# Installation

## Adding a trigger to the Attachments area of the video editor

After installing, you will need to place the relevant triggers into your theme.  For example, you might start around line 99 of the default theme file account/videos_edit.phtml.  Adding the following plugin event trigger there allows the plugin to format attachment form elements as needed:

```php
<?php Plugin::triggerEvent( 'videos.edit.attachment.list', $file[0]->fileId, $video->videoId ); ?>
```

### Adding thumbnails to the theme 

The above change will let you change which images are used as thumbs/posters.  To get the site to pick these changes up, you may need to edit the various theme areas appropriately.  For example, in account/videos.phtml you could do this:

```php
<img width="165" height="92" src="<?php Plugin::triggerEvent('theme.thumbnail.url',$video-
>videoId);?>" />
```

Repeat for other places that thumbnails or posters appear, such as the default HTML5 video element in watch.phtml:  

```php
<video class="video-js vjs-default-skin" data-setup='{ "controls": true, "autoplay": false, "preload":      "auto" }' width="600" height="337" poster="<?php Plugin::triggerEvent('theme.thumbnail.url',$video->videoId);?>">

```

