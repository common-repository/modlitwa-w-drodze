<?php
/*
Plugin Name: Modlitwa w drodze
Description: "Modlitwa w drodze" - korzystaj na swojej stronie.
Version: 1.0.3
Author: Gembit
Author URI: http://gembit.pl
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die('No script kiddies please!');

class MWD_Plugin
{
	private $podcastUrl = 'https://www.modlitwawdrodze.pl/podcast.xml';
	public $logoUrl = '//modlitwawdrodze.pl/assets/img/logo_modlitwa_w_drodze_full.png';

	private static $_instance = null;

	/**
	 * @return MWD_Plugin
	 */
	public static function instance()
	{
		if(is_null(self::$_instance))
		{
			self::$_instance = new self;
		}
		return self::$_instance;
	}

	public function __construct()
	{
		add_action('widgets_init', array($this, 'register_widget'));
		add_action( 'modlitwa_w_drodze_feed', array($this, 'feed') );
		register_activation_hook(__FILE__, array($this, 'feed'));
		add_action( 'wp_enqueue_scripts', array($this, 'initCss') );

		if( !wp_next_scheduled( 'modlitwa_w_drodze_feed' ) )
		{
			wp_schedule_event( time(), 'daily', 'modlitwa_w_drodze_feed' );
		}
	}

	public function initCss()
	{
		if ( is_active_widget( false, false, 'modlitwa_w_drodze', true ) )
		{
			wp_enqueue_style( 'mwd-style', plugins_url('style.css', __FILE__) );
		}
	}

	/**
	 * @return $this
	 */
	public function register_widget()
	{
		register_widget( 'MWD_Widget' );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function feed()
	{
		$xmlFileContent = file_get_contents( $this->podcastUrl );

		$xmlFile = new DOMDocument;
		$xmlFile->loadXML( $xmlFileContent );

		$entries = array();

		$now = new DateTime();
		$now->setTime( 0, 0, 0 );
		foreach( $xmlFile->getElementsByTagName( 'item' ) as $item )
		{
			$date = trim( $item->getElementsByTagName( 'pubDate' )->item( 0 )->nodeValue );
			$date = new DateTime( $date );
			$date->setTime( 0, 0, 0 );

			$title = trim( $item->getElementsByTagName( 'title' )->item( 0 )->nodeValue );
			$description = trim( $item->getElementsByTagName( 'description' )->item( 0 )->nodeValue );
			$file = $item->getElementsByTagName( 'enclosure' )->item( 0 )->getAttribute( 'url' );

			$entries[ $date->format( "Y-m-d" ) ] = array(
					'title' => $title,
					'desc' => $description,
					'file' => $file
			);
		}

		$this->_setEntries($entries);
		return $this;
	}

	/**
	 * @param array $entries
	 * @return $this
	 */
	private function _setEntries(array $entries)
	{
		update_option( 'modlitwa_w_drodze', $entries );
		return $this;
	}

	/**
	 * @return array
	 */
	public function getEntries()
	{
		return get_option( 'modlitwa_w_drodze', array() );
	}
}

class MWD_Widget extends WP_Widget
{

	public function __construct()
	{
		parent::__construct(
			'modlitwa_w_drodze', // Base ID
			'Modlitwa w drodze' // Name
		);
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance )
	{
		$titleWidget = null;
		if ( ! empty( $instance['title'] ) )
		{
			$titleWidget = $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}

		$entries = MWD_Plugin::instance()->getEntries();

		$title = null;
		$description = null;
		$file = null;

		$now = new DateTime();
		$now->setTime( 0, 0, 0 );
		foreach( $entries as $date => $item )
		{
			$date = new DateTime( $date );
			$date->setTime( 0, 0, 0 );
			if( $date==$now )
			{
				$title = $item[ 'title' ];
				$description = $item[ 'desc' ];
				$file = $item[ 'file' ];
			}
		}

		$logo = MWD_Plugin::instance()->logoUrl;
		$player = do_shortcode( '[audio src="' . $file . '"][/audio]' );
		$before_widget = $args[ 'before_widget' ];
		$after_widget = $args[ 'after_widget' ];

		echo <<<HTML
{$before_widget}
{$titleWidget}
<div>
	<a href="http://www.modlitwawdrodze.pl" target="_blank" class="mwd-logo-link">
		<img src="{$logo}" class="mwd-logo">
	</a>
	<p class="mwd-text">
		{$title}
		<br/>
		{$description}
	</p>
	<div class="mwd-player">
		{$player}
	</div>
</div>
{$after_widget}
HTML;

	}

	public function form( $instance )
	{
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update($new_instance, $old_instance)
	{
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}

}

MWD_Plugin::instance();