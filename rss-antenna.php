<?php
/*
 Plugin Name: RSS Antenna
Plugin URI: http://residentbird.main.jp/bizplugin/
Description: Webサイトの更新情報をRSSから取得し更新日時の新しい順に一覧表示するプラグインです。
Version: 1.8.0
Author:Hideki Tanaka
Author URI: http://residentbird.main.jp/bizplugin/
*/


if ( !class_exists( 'RssImage' ) ) {
	include_once( dirname(__FILE__) . "/class.image.php" );
}

new RssAntennaPlugin();

class RA{
	public static function remove_cache_map($options) {
		$options["cache_map"] = "";
		$options["cache_date"] = "";
		update_option(RssAntennaPlugin::OPTION_NAME, $options);
	}

	public static function create_cache_dir(){
		$upload_dir = self::get_upload_dir();
		if (!file_exists($upload_dir)){
			mkdir($upload_dir);
		}
	}

	public static function get_upload_dir(){
		$upload_array = wp_upload_dir();
		return $upload_array["basedir"]. "/rsscache/";
	}

	public static function get_upload_url( $filename ){
		$upload_array = wp_upload_dir();
		return $upload_array["baseurl"]. "/rsscache/". $filename;
	}

	public static function delete_cache_dir(){
		$path = self::get_upload_dir();
		self::remove_dir($path);
		if(is_dir($path)){
			rmdir($path);
		}
	}

	public static function clear_cache_files(){
		$path = self::get_upload_dir();
		self::remove_dir($path);
	}

	private static function remove_dir($dir) {
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				self::remove_dir($file);
			else
				unlink($file);
		}
	}
}

/**
 * プラグイン本体
 */
class RssAntennaPlugin{

	const SHORTCODE = "showrss";
	const OPTION_NAME = "rss_antenna_options";
	const PLUGIN_DIR = '/rss-antenna/';
	const CSS_FILE = 'rss-antenna.css';

	public function __construct(){
		register_activation_hook(__FILE__, array(&$this,'on_activation'));
		register_deactivation_hook(__FILE__, array(&$this,'on_deactivation'));

		add_action( 'admin_init', array(&$this,'on_admin_init') );
		add_action( 'admin_menu', array(&$this, 'on_admin_menu'));
		add_action( 'wp_enqueue_scripts', array(&$this,'on_enqueue_scripts'));
		add_shortcode(self::SHORTCODE, array(&$this,'show_shortcode'));
		add_filter('widget_text', 'do_shortcode');
	}

	function on_activation() {
		RA::create_cache_dir();
		$tmp = get_option(self::OPTION_NAME);
		if(!is_array($tmp)) {
			$arr = array(
					"feeds" => "http://residentbird.main.jp/bizplugin/feed/\n",
					"feed_count" => "10",
					"adblock" => "on",
					"description" => "on",
					"image" => "on",
					"image_position" => "右",
			);
			update_option(self::OPTION_NAME, $arr);
		}
	}

	function on_deactivation(){
		$options = get_option(self::OPTION_NAME);
		RA::remove_cache_map($options);
		unregister_setting(self::OPTION_NAME, self::OPTION_NAME );
		wp_deregister_style('rss-antenna-style');
		RA::delete_cache_dir();
	}


	function on_admin_init() {
		register_setting(self::OPTION_NAME, self::OPTION_NAME);
		add_settings_section('main_section', '設定', array(&$this,'section_text_fn'), __FILE__);
		add_settings_field('id_feeds', 'RSS(10件まで登録可)', array(&$this,'setting_feeds'), __FILE__, 'main_section');
		add_settings_field('rss_number', '表示件数', array(&$this,'setting_number'), __FILE__, 'main_section');
		add_settings_field('id_description', '記事の抜粋を表示する', array(&$this,'setting_description_chk'), __FILE__, 'main_section');
		add_settings_field('id_image', '　サムネイル画像を表示する', array(&$this,'setting_image_chk'), __FILE__, 'main_section');
		add_settings_field('id_image_position', '　サムネイル画像表示位置', array(&$this,'setting_image_position'), __FILE__, 'main_section');
		add_settings_field('id_adblock', '広告を表示しない', array(&$this,'setting_adblock_chk'), __FILE__, 'main_section');
		wp_register_style( 'rss-antenna-style', plugins_url('rss-antenna.css', __FILE__) );
	}

	function on_enqueue_scripts() {
		$cssPath = WP_PLUGIN_DIR . self::PLUGIN_DIR . self::CSS_FILE;
		$this->aaa = $cssPath;
		if(file_exists($cssPath)){
			$cssUrl = plugins_url('rss-antenna.css', __FILE__);
			wp_register_style('rss-antenna-style', $cssUrl);
			wp_enqueue_style('rss-antenna-style');
		}
	}

	public function on_admin_menu() {
		add_options_page("RSS Antenna設定", "RSS Antenna設定", 'administrator', __FILE__, array(&$this, 'show_admin_page'));
	}

	public function show_admin_page() {
		$file = __FILE__;
		$option_name = self::OPTION_NAME;
		$shortcode = "[" . self::SHORTCODE . "]";
		include_once( dirname(__FILE__) . '/admin-view.php');
	}

	function show_rss_antenna(){

		$info = new RssInfo(self::OPTION_NAME);
		include( dirname(__FILE__) . '/rss-antenna-view.php');
	}

	function show_shortcode(){
		ob_start();
		$this->show_rss_antenna();
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	function  section_text_fn() {
		//echo '<p>Below are some examples of different option controls.</p>';
	}

	function  setting_number() {
		$options = get_option(self::OPTION_NAME);
		$items = array("5", "10", "15", "20","25", "30");
		echo "<select id='rss_number' name='rss_antenna_options[feed_count]'>";
		foreach($items as $item) {
			$selected = ($options['feed_count']==$item) ? 'selected="selected"' : '';
			echo "<option value='$item' $selected>$item</option>";
		}
		echo "</select>";
	}

	function setting_description_chk() {
		$this->setting_chk( "description" );
	}

	function setting_image_chk() {
		$this->setting_chk( "image" );
	}

	function setting_adblock_chk() {
		$this->setting_chk( "adblock" );
	}

	function setting_chk( $id ) {
		$options = get_option(self::OPTION_NAME);
		$checked = (isset($options[$id]) && $options[$id]) ? $checked = ' checked="checked" ': "";
		$name = self::OPTION_NAME. "[$id]";
		echo "<input ".$checked." id='id_".$id."' name='".$name."' type='checkbox' />";
	}

	function setting_feeds() {
		$this->setting_textarea("feeds");
	}

	function setting_image_position() {
		$options = get_option(self::OPTION_NAME);
		$name = "image_position";
		$value = empty( $options[ $name ] ) ? "右" : $options[ $name ];
		$items = array("右", "左");
		echo "<select id='{$name}' name='rss_antenna_options[{$name}]'>";
		foreach($items as $item) {
			$selected = ( $value == $item) ? 'selected="selected"' : '';
			echo "<option value='$item' $selected>$item</option>";
		}
		echo "</select>";
	}

	function setting_textarea( $name ) {
		$options = get_option(self::OPTION_NAME);
		$value = $options[ $name ];
		echo "<textarea id='{$name}' name='rss_antenna_options[{$name}]' rows='10' cols='70' wrap='off'>{$value}</textarea>";
	}
}

/**
 * Rss一覧に表示する内容
 *
 */
class RssInfo{
	var $setting;
	var $image_position;
	var $description_position;
	var $items = array();
	const MAX_FEED = 10;
	const CATCH_TIME = 3600; //1時間

	public function __construct($option_name){
		$this->setting = get_option($option_name);
		$this->createItems();
		$this->image_position = (empty($this->setting['image_position']) || $this->setting['image_position'] == "右")? "right" : "left";
		$this->description_position = $this->image_position == "right" ? "left" : "right";
	}

	const USER_AGENT = 'SIMPLEPIE_USERAGENT';

	private function createItems(){
		$feed_count = $this->setting['feed_count'];
		$feed_urls = $this->getFeedArray($this->setting['feeds']);

		if ( !is_array($feed_urls)){
			return;
		}

		global $wp_version;
		if (version_compare($wp_version, '3.5', '>=')) {
			$rss = $this->fetch_feed($feed_urls);
		}
		else{
			$rss = fetch_feed($feed_urls);
		}

		if ( is_wp_error( $rss )){
			foreach( (array)$rss->get_error_message() as $msg ){
				echo "<p>". $msg."</p>";
			}
			return null;
		}
		$rss->set_cache_duration( self::CATCH_TIME );
		$rss->set_useragent( self::USER_AGENT );
		$rss->init();
		$maxitems = $rss->get_item_quantity($feed_count);
		$rss_items = $rss->get_items(0, $maxitems);
		date_default_timezone_set('Asia/Tokyo');

		$duplicate = array();
		foreach($rss_items as $item){
			$url = esc_url($item->get_permalink());
			if ( empty($url) || isset( $duplicate[$url] ) ){
				continue;
			}
			$duplicate[$url] = true;
			if ( isset($this->setting["adblock"]) && $this->isAd($item->get_title() ) ){
				continue;
			}
			$this->items[] = new RssItem($item);
		}
	}

	private function fetch_feed($url) {
		require_once (ABSPATH . WPINC . '/class-feed.php');

		$feed = new SimplePie();

		$feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
		// We must manually overwrite $feed->sanitize because SimplePie's
		// constructor sets it before we have a chance to set the sanitization class
		$feed->sanitize = new WP_SimplePie_Sanitize_KSES();

		$feed->set_cache_class( 'WP_Feed_Cache' );
		$feed->set_file_class( 'WP_SimplePie_File' );

		$feed->set_feed_url($url);
		$feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', 12 * HOUR_IN_SECONDS, $url ) );
		do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );
		$feed->set_useragent( self::USER_AGENT );
		$feed->init();
		$feed->handle_content_type();
		if ( $feed->error() )
			return new WP_Error('simplepie-error', $feed->error());

		return $feed;
	}

	private function getFeedArray($text){
		$array = explode("\n", $text, self::MAX_FEED + 1);
		if ( isset($array[self::MAX_FEED]) ){
			unset( $array[self::MAX_FEED] );
		}
		$array = array_map('trim', $array);
		$array = array_filter($array, 'strlen');
		$array = array_unique($array);
		return ($array);
	}

	private function isAd($title){
		$adtags = array("AD:", "PR:", ": PR", "\[PR\]", "\[AD\]");
		foreach( $adtags as $tag){
			$pattern = "/^{$tag}|{$tag}$/";
			if ( preg_match($pattern, $title) == 1 ){
				return true;
			}
		}
		return false;
	}
}
/**
 * 個々のRss項目の内容
 *
 */
class RssItem{
	var $date;
	var $title;
	var $url;
	var $site_name;
	var $description;
	var $img_src;
	const DESCRIPTION_SIZE = 400;

	public function __construct( $feed ){
		$this->date = $feed->get_date("Y/m/d H:i");
		$this->title = esc_html( $feed->get_title());
		$this->url = esc_url($feed->get_permalink());
		$this->site_name = esc_html($feed->get_feed()->get_title());

		$options = get_option(RssAntennaPlugin::OPTION_NAME);

		if ( !isset( $options["description"])  ){
			return;
		}
		$text = strip_tags ($feed->get_content());
		$this->description = mb_strimwidth( $text, 0, self::DESCRIPTION_SIZE,"…");
		if ( !isset( $options["image"])  ){
			return;
		}
		$this->img_src = $this->get_image_src($feed->get_content());
	}

	private function get_image_src($content) {
		$cache_img_url = $this->get_image_cache($this->url);
		if ( isset($cache_img_url) ){
			return $cache_img_url;
		}

		$img_file = $this->get_image_file($content);
		if ( empty($img_file)){
			return null;
		}

		$local_img_url = $this->save_image_file($img_file);
		$this->update_image_cache($this->url, $local_img_url);
		return $local_img_url;
	}

	private function get_image_cache($key){
		$options = get_option(RssAntennaPlugin::OPTION_NAME);
		if ( !isset( $options["cache_map"]) || !isset($options["cache_date"]) ){
			return null;
		}
		if ( $options["cache_date"] != date_i18n( "Y/m/d") ){
			RA::clear_cache_files();
			RA::remove_cache_map($options);
			return null;
		}
		if( isset( $options["cache_map"][$key] ) ){
			return $options["cache_map"][$key];
		}
		return null;
	}

	private function get_image_file($content) {
		$searchPattern = '/<img.+?src=[\'"]([^\'"]+?)[\'"].*?>/msi';
		if ( preg_match_all( $searchPattern, $content, $matches ) ) {
			$feed_img_urls = $matches[1];
		}
		if ( empty($feed_img_urls)){
			return null;
		}

		foreach ( $feed_img_urls as $feed_img_url){
			$response = wp_remote_get($feed_img_url, array( 'timeout' => 3 ));
			if( is_wp_error( $response ) ) {
				return;
			}
			if ( isset($response['body']) && $this->isIcon($response['body']) == false){
				return $response['body'];
			}
		}
		return null;
	}

	private function update_image_cache($url, $img_url){
		$options = get_option(RssAntennaPlugin::OPTION_NAME);
		$map = isset( $options["cache_map"] ) ? $options["cache_map"] : array();
		$map[$url] = $img_url;
		$options["cache_map"] = $map;
		$options["cache_date"] = date_i18n( "Y/m/d");
		update_option(RssAntennaPlugin::OPTION_NAME, $options);
	}

	private function save_image_file($image){
		RA::create_cache_dir();
		$filename = uniqid();
		$upload_dir = RA::get_upload_dir();
		file_put_contents($upload_dir.$filename,$image);
		$this->resize($upload_dir.$filename);
		$upload_url = RA::get_upload_url($filename);
		return $upload_url;
	}

	private function resize( $path ){
		if ( !function_exists("imagecreatefromjpeg")){
			return;
		}
		$thumb = new RssImage( $path );
		if ( $thumb->image_width <= 150 ){
			return;
		}
		$thumb->width( 120 );
		$thumb->save();
		unset($thumb);
	}

	const MIN_SIZE = "40";
	const MAX_SIZE = "1600";
	private function isIcon($data) {
		$img = base64_encode($data);
		$scheme = 'data:application/octet-stream;base64,';
		list($width, $height)  = getimagesize($scheme . $img);
		unset($img);
		if( $width <= self::MIN_SIZE || $height <= self::MIN_SIZE || $width > self::MAX_SIZE || $height > self::MAX_SIZE){
			return true;
		}
		return false;
	}
}