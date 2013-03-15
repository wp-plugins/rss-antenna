<?php
/*
 Plugin Name: RSS Antenna
Plugin URI: http://residentbird.main.jp/bizplugin/
Description: Webサイトの更新情報をRSSから取得し更新日時の新しい順に一覧表示するプラグインです。
Version: 1.5.0
Author:WordPress Biz Plugin
Author URI: http://residentbird.main.jp/bizplugin/
*/


$rssAntennaPlugin = new RssAntennaPlugin();

/**
 * プラグイン本体
 */
class RssAntennaPlugin{

	const SHORTCODE = "showrss";  //ショートコード
	const OPTION_NAME = "rss_antenna_options"; //オプション設定値
	const PLUGIN_DIR = '/rss-antenna/'; //プラグインのディレクトリ名
	const CSS_FILE = 'rss-antenna.css'; //cssファイル名

	public function __construct(){
		register_activation_hook(__FILE__, array(&$this,'on_activation'));	//プラグイン有効時の処理を設定
		register_deactivation_hook(__FILE__, array(&$this,'on_deactivation'));

		add_action( 'admin_init', array(&$this,'on_admin_init') );	//管理画面の初期化
		add_action( 'admin_menu', array(&$this, 'on_admin_menu'));			//管理画面にメニューを追加
		add_action( 'wp_enqueue_scripts', array(&$this,'on_enqueue_scripts'));				//cssの設定（管理画面以外)
		add_shortcode(self::SHORTCODE, array(&$this,'show_shortcode')); 		//ショートコードの設定
		add_filter('widget_text', 'do_shortcode');
	}

	function on_activation() {
		$tmp = get_option(self::OPTION_NAME);
		if(!is_array($tmp)) {
			$arr = array(
					"feeds" => "http://residentbird.main.jp/bizplugin/feed/\n",
					"feed_count" => "10",
					"adblock" => "on",
					"description" => "on",
					"image" => "on",
			);
			update_option(self::OPTION_NAME, $arr);
		}
	}

	function on_deactivation(){
		unregister_setting(self::OPTION_NAME, self::OPTION_NAME );
		wp_deregister_style('rss-antenna-style');
	}


	function on_admin_init() {
		register_setting(self::OPTION_NAME, self::OPTION_NAME);
		add_settings_section('main_section', '設定', array(&$this,'section_text_fn'), __FILE__);
		add_settings_field('id_feeds', 'RSS(10件まで登録可)', array(&$this,'setting_feeds'), __FILE__, 'main_section');
		add_settings_field('rss_number', '表示件数', array(&$this,'setting_number'), __FILE__, 'main_section');
		add_settings_field('id_description', '記事の抜粋を表示する', array(&$this,'setting_description_chk'), __FILE__, 'main_section');
		add_settings_field('id_image', '　サムネイル画像を表示する', array(&$this,'setting_image_chk'), __FILE__, 'main_section');
		add_settings_field('id_adblock', '広告を表示しない', array(&$this,'setting_adblock_chk'), __FILE__, 'main_section');
		wp_register_style( 'rss-antenna-style', plugins_url('rss-antenna.css', __FILE__) );
	}

	function on_enqueue_scripts() {
		$cssPath = WP_PLUGIN_DIR . self::PLUGIN_DIR . self::CSS_FILE;
		$this->aaa = $cssPath;
		if(file_exists($cssPath)){
			/* CSSの格納URL */
			$cssUrl = plugins_url('rss-antenna.css', __FILE__);
			/* CSS登録 */
			wp_register_style('rss-antenna-style', $cssUrl);
			/* CSS追加 */
			wp_enqueue_style('rss-antenna-style');
		}
	}

	public function on_admin_menu() {
		$page = add_options_page("RSS Antenna設定", "RSS Antenna設定", 'administrator', __FILE__, array(&$this, 'show_admin_page'));
	}

	public function show_admin_page() {
		$file = __FILE__;
		$option_name = self::OPTION_NAME;
		$shortcode = "[" . self::SHORTCODE . "]";
		include_once('admin-view.php');
	}

	function show_rss_antenna(){

		$info = new RssInfo(self::OPTION_NAME);
		include('rss-antenna-view.php');
	}

	function show_shortcode(){
		ob_start();
		$this->show_rss_antenna();
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	// Section HTML, displayed before the first option
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
	var $items = array();
	const MAX_FEED = 10;
	const CATCH_TIME = 3600; //1時間

	public function __construct($option_name){
		$this->setting = get_option($option_name);
		$this->createItems();
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
			if ( empty($url) || $duplicate[$url] == true ){
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
		$adtags = array("AD:", "PR:", "\[PR\]", "\[AD\]");
		foreach( $adtags as $tag){
			$pattern = "/^{$tag}/";
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
	var $img_tag;
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
		$this->img_tag = $this->get_img($feed->get_content());
	}



	private function get_img($content) {
		$cache_img = $this->get_image_cache($this->url);
		if ( isset($cache_img) ){
			return "<img src='{$cache_img}'>";
		}

		$searchPattern = '/<img.+?src=[\'"]([^\'"]+?)[\'"].*?>/msi';
		if ( preg_match_all( $searchPattern, $content, $matches ) ) {
			$feed_imgs = $matches[1];
		}
		if ( empty($feed_imgs)){
			return null;
		}

		foreach ( $feed_imgs as $img){
			if ( !$this->isIcon($img) ){
				$feed_img = $img;
				break;
			}
		}
		if ( empty($feed_img) ){
			return null;
		}

		$feed_img = $this->update_image_cache($this->url, $feed_img);
		if ( !isset($feed_img)){
			return null;
		}
		return "<img src='{$feed_img}'>";
	}

	private function get_image_cache($key){
		$options = get_option(RssAntennaPlugin::OPTION_NAME);
		if ( !isset( $options["cache_map"]) || !isset($options["cache_date"]) ){
			return null;
		}
		$upload_array = wp_upload_dir();
		$cachePath = $upload_array["basedir"] . "/rsscache/";
		if ( $options["cache_date"] != date( "Y/m/d", time()) ){
			$this->remove_dir($cachePath);
			$this->remove_cache_map($options);
			return null;
		}
		if( isset( $options["cache_map"][$key] ) ){
			return $options["cache_map"][$key];
		}
		return null;
	}

	private function update_image_cache($url, $img){

		$options = get_option(RssAntennaPlugin::OPTION_NAME);
		$img_url = $this->save_image_file($img);

		$map = $options["cache_map"];
		if ( !is_array($map) ){
			$map = array();
		}
		$map[$url] = $img_url;
		$options["cache_map"] = $map;
		$options["cache_date"] = date( "Y/m/d", time());
		update_option(RssAntennaPlugin::OPTION_NAME, $options);

		return $img_url;
	}

	private function save_image_file($file_url){
		$image = $this->get_image_file($file_url);

		if ( !$image ){
			return null;
		}
		$filename = uniqid();
		$upload_array = wp_upload_dir();
		$upload_dir = $upload_array["basedir"]. "/rsscache/";
		$upload_url = $upload_array["baseurl"]. "/rsscache/". $filename;

		if (!file_exists($upload_dir)){
			mkdir($upload_dir);
		}
		file_put_contents($upload_dir.$filename,$image);
		return $upload_url;
	}

	function get_image_file($url) {
		$img = @file_get_contents( $url, false,
				stream_context_create(array(
						'http' => array(
								'method'  => 'GET',
								'timeout'=>3.0
						)
				))
		);

		if($img === FALSE){
			return null;
		}
		return $img;
	}

	function remove_cache_map($options) {
		$options["cache_map"] = "";
		$options["cache_date"] = "";
		update_option(RssAntennaPlugin::OPTION_NAME, $options);
	}

	function remove_dir($dir) {
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				$this->remove_dir($file);
			else
				unlink($file);
		}
	}

	const MIN_SIZE = "40";
	private function isIcon($img) {
		list($width, $height) = @getimagesize($img);
		if( $width <= self::MIN_SIZE || $height <= self::MIN_SIZE ){
			return true;
		}
		return false;
	}
}

?>