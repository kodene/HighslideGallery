<?php
/*
 * HighslideGallery class.
 */

if ( !defined( 'MEDIAWIKI' ) )
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );

class HighslideGallery {
	/**
	 * Contains an id used to define highslide galleries.
	 * 
	 * @var string
	 */
    private static $hgID;
	
	/**
	 * Used for determining if an image is included in a multiple image gallery.
	 * 
	 * @var boolean
	 */
    private static $isGallery = false;
	
	/**
	 * Uses the Resource Loader to add the javacript and css files.
	 * 
	 * @param Page $out
	 * @param Skin $skin
	 * @return boolean
	 */
    public static function AddResources (&$out, $skin) {
		$out->addModules('ext.highslideGallery');
		
        return true;
    }

	/**
	 * Adds parser hooks.
	 * 
	 * @global HighlideGallery $hg
	 * @param Parser $parser
	 * @return boolean
	 */
	public static function AddHooks (&$parser) {
		$parser->setHook('hsyoutube', 'HighslideGallery::MakeYouTubeLink');
		$parser->setFunctionHook('hsimg', 'HighslideGallery::MakeExtLink');
	
		return true;
	}

	/**
	 * Checks for "highslide" use in the caption of an Image function. If it
	 * exists, the highslide text is stripped from the caption, alt, and title.
	 * The $dummy parser object is used to call MakeImageLink2 a second time in
	 * order to create the HTML for the image, which is then processed to enable
	 * Highslide.
	 * 
	 * @param Parser $dummy
	 * @param Title $title
	 * @param File $file
	 * @param array $frameParams
	 * @param array $handlerParams
	 * @param string $time
	 * @param string $res
	 * @return boolean
	 */
    public static function MakeImageLink (&$dummy, &$title, &$file, &$frameParams, &$handlerParams, &$time, &$res) {
		$galleryID = array();
		
		if (preg_match('/^highslide[^:]*:/', $frameParams['caption'])) {
			// Check to see if an id is set.
			preg_match('/^highslide=(?!:)([a-zA-Z0-9]+)/i', $frameParams['caption'], $galleryID);

			// If an ID is set, remember it, otherwise set to null. This is used
			// later on to determine what overlays to add.
			if (isset($galleryID[1])) {
				self::$hgID = $galleryID[1];
			} else {
				self::$hgID = null;
			}

			// Remove highslide syntax from the caption.
			$frameParams['caption'] = preg_replace('/^highslide([^:]*):/', '', $frameParams['caption']);

			// Remove highslide syntax from the alt attribute.
			if (isset($frameParams['alt'])) {
				$frameParams['alt'] = preg_replace('/^highslide([^:]*):/', '', $frameParams['alt']);
			}
			
			// Remove highslide syntax from the title attribute.
			if (isset($frameParams['title'])) {
                $frameParams['title'] = preg_replace('/^highslide([^:])*:/', '', $frameParams['title']);
            }
			
			// Use the dummy linker object to create the HTML.
			$res = $dummy->MakeImageLink2($title, $file, $frameParams, $handlerParams);

			// Highslide time.
			self::AddHighslide($res, $file, $frameParams['caption'], $title);
				
			return false;
		}

		return true;
    }

	/**
	 * Processes the 'hsyoutube' tag to enable viewing the video in Highslide.
	 * All tag parameters are optional except the URL (if it is missing, the
	 * function returns false and nothing is displayed).
	 * 
	 * @param string $content
	 * @param array $attributes
	 * @param Parser $parser
	 * @return boolean|string
	 */
    public static function MakeYouTubeLink ($content, array $attributes, $parser) {
        // Attempt to get the embed code
        if (preg_match('/[?&]v=([^?&]+)/', $content, $embedCode)) {
            $code = $embedCode[1];
        } elseif (preg_match('/embed\/([^??]+)/', $content, $embedCode)) {
            $code = $embedCode[1];
        } else {
            return false;
        }

        // Process attributes
        // title    - Text for the link
        // autoplay - Enables auto play for the video (no value) 
        if (!isset($attributes['title'])) {
            $title = "YouTube Video";
        } else {
            $title = $attributes['title'];
        }

        if (!array_key_exists('autoplay', $attributes)) {
            $autoplay = "?";
        } else {
            $autoplay = "?autoplay=1&amp;";
        }

        $s = "<a class=\"highslide link-youtube\" onclick=\"return hs.htmlExpand(this, videoOptions)\" title=\"${title}\"";
        $s = $s . " href=\"http://www.youtube.com/embed/${code}${autoplay}autohide=1&amp;rel=0\">";
        $s = $s . $title . "</a>";

		if (isset($attributes['caption'])) {
			$s = $s . "<div class='highslide-caption'>" . $attributes['caption'] . "</div>";
		}
        return $s;
    }
    
	/**
	 * Creates a link to an external image and enables Highslide. Parameters
	 * are passed in by a hook call.
	 * 
	 * @param Parser $parser
	 * @param string $hsid
	 * @param string $width
	 * @param string $title
	 * @param string $content
	 * @return string Highslide enabled HTML for an external image.
	 */
	public static function MakeExtLink ($parser, $hsid, $width, $title, $content) {
		// Process attributes
		// width	- Maximum width of image in page
		// title	- Becomes the caption in Highslide
		// hsid		- Gallery id
		if ($content == '') {
			return false;
		}
		$hs = "<a href=\"$content\" class=\"image\"";
		$hsimg = "<img class=\"hsimg\" src=\"".$content."\"";

		if ($title) {
			$caption = $title;
		} else {
			$caption = $content;
		}

		$hsimg = $hsimg . " alt=\"".$caption."\"";
		$hs = $hs . " title=\"".$caption."\"";
		
		if (isset($width)) {
			$hsimg = $hsimg . " style=\"max-width: ".$width."px\"";
		}
		if (isset($hsid)) {
			self::$hgID = $hsid;
		}
		
		$s = $hs . ">" . $hsimg . " /></a>";
		self::AddHighSlide($s, "", $caption, "");
		return array($s, 'isHTML' => true);
	}
    
	/**
	 * Processes HTML to enable Highslide on images.
	 * 
	 * @global string $wgStylePath
	 * @param string $s
	 * @param File $file
	 * @param string $caption
	 * @param Title $title
	 */
	private static function AddHighslide (&$s, $file, $caption, $title) {
		global $wgStylePath;

        if ($caption == "") {
            $caption = $file->getName();
        }

		if (!empty($title)) {
			$url = $title->getLocalURL();
			$caption = "<a href=\'$url\' class=\'internal\'><img src=\'../$wgStylePath/common/images/magnify-clip.png\' width=\'15\' height=\'11\' alt=\'\'></img></a> ".$caption;		
		}
		
        if (!isset(self::$hgID)) {
            self::$hgID = uniqid();
        } else {
            if (!self::$isGallery) {
                self::$isGallery = 1;
            }
        }
        
        // Start building the necessary information for the "a" element.
        $hs = "id=\"".self::$hgID."\" onClick=\"return hs.expand(this, {slideshowGroup:'".self::$hgID."',captionText:'".$caption."'";
        if (self::$isGallery) {
            $hs = $hs . "})\" href";
			$s = preg_replace('/<img /', "<img id=\"wikigallery\" ", $s, 1);
        } else {
            $hs = $hs . ",numberPosition:'none'})\" href";
        }
		
        // Add highslide attributes to the first <a> elem
        $s = preg_replace('/href/', $hs, $s, 1);
    
        // Need the full URL for highslide to display it (support Short URL's).
        if(!empty($file)) {
			$url = $file->getUrl();
			$pattern = '/href="([^"]*)*"/i';
			$s = preg_replace($pattern, "href=\"$url\"", $s, 1);
		}
		self::$isGallery = 0;
    }
}