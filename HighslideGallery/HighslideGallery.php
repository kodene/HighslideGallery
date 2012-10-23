<?php
/**
 * HighslideGallery extension entry point.
 */

if ( !defined( 'MEDIAWIKI' ) )
    die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );

$wgExtensionMessagesFiles['HighslideGallery'] = dirname( __FILE__ ) . '/HighslideGallery.i18n.php';
$wgExtensionCredits['parserhook'][] = array(
    'path'              => __FILE__,
    'name'              => 'HighslideGallery',
    'url'               => 'none',
    'author'            => 'Brian McCloskey',
    'descriptionmsg'    => 'hg-desc',
    'version'           => '1.0.0'
);

$wgAutoloadClasses['HighslideGallery'] = dirname( __FILE__ ) . '/HighslideGallery.body.php';

$wgResourceModules['ext.highslideGallery'] = array(
		'scripts'	=> array('highslide.js','highslide.cfg.js'),
		'styles'	=> array('highslide.css','highslide.override.css'),
		'position'	=> 'top',
		'localBasePath'	=> dirname(__FILE__) . '/modules',
		'remoteExtPath'	=> 'HighslideGallery/modules',
);

//$hg = new HighslideGallery;

$wgHooks['ImageBeforeProduceHTML'][]	= 'HighslideGallery::MakeImageLink';
$wgHooks['BeforePageDisplay'][]		= 'HighslideGallery::AddResources';
$wgHooks['ParserFirstCallInit'][]		= 'HighslideGallery::AddHooks';
