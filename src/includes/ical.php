<?php
if ( ! defined( 'WPINC' ) ) {
	exit;
}

require_once EL_PATH . 'includes/options.php';
require_once EL_PATH . 'includes/events.php';

// This class handles iCal feed
class EL_ICal {

	private static $instance;

	private $options;

	private $events;


	public static function &get_instance() {
		// Create class instance if required
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		// Return class instance
		return self::$instance;
	}


	private function __construct() {
		$this->options = EL_Options::get_instance();
		$this->events  = EL_Events::get_instance();
		$this->init();
	}


	public function init() {
		// register feed properly with WordPress
		add_feed( $this->get_feed_name(), array( &$this, 'print_ical' ) );
	}


	public function print_ical() {
		header( 'Content-Type: text/calendar; charset=' . get_option( 'blog_charset' ), true );
		$options = array(
			'date_filter' => $this->options->get( 'el_feed_ical_upcoming_only' ) ? 'upcoming' : null,
			'order'       => array( 'startdate DESC', 'starttime DESC', 'enddate DESC' ),
		);

		$events = $this->events->get( $options );

		// Print iCal
		$eol = "\r\n";
		echo 'BEGIN:VCALENDAR' . $eol .
		'VERSION:2.0' . $eol .
		'PRODID:-//' . get_bloginfo( 'name' ) . '//NONSGML v1.0//EN' . $eol .
		'CALSCALE:GREGORIAN' . $eol .
		'UID:' . md5( uniqid( mt_rand(), true ) ) . '@' . get_bloginfo( 'name' ) . $eol;

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				echo 'BEGIN:VEVENT' . $eol .
					'UID:' . md5( uniqid( mt_rand(), true ) ) . '@' . get_bloginfo( 'name' ) . $eol .
					'DTSTART:' . mysql2date( 'Ymd', $event->startdate, false ) . get_gmt_from_date( $event->starttime, '\THis\Z' ) . $eol;
				if ( $event->enddate !== $event->startdate ) {
					echo 'DTEND:' . mysql2date( 'Ymd', $event->enddate, false ) . $eol;
				}
				echo 'DTSTAMP:' . date( 'Ymd\THis\Z' ) . $eol .
					'LOCATION:' . $event->location . $eol .
					'SUMMARY:' . $this->sanitize_feed_text( $event->title ) . $eol;
				if ( ! empty( $event->content ) ) {
					echo 'DESCRIPTION:' . $this->sanitize_feed_text( str_replace( array( "\r", "\n" ), ' ', $event->content ) ) . $eol;
				}
				echo 'END:VEVENT' . $eol;
			}
		}
		echo 'END:VCALENDAR';
	}


	public function update_ical_rewrite_status() {
		$feeds               = array_keys( (array) get_option( 'rewrite_rules' ), 'index.php?&feed=$matches[1]' );
		$feed_rewrite_status = 0 < count( preg_grep( '@[(\|]' . $this->get_feed_name() . '[\|)]@', $feeds ) );
		// if iCal is enabled but rewrite rules do not exist already, flush rewrite rules
		if ( '1' == $this->options->get( 'el_feed_enable_ical' ) && ! $feed_rewrite_status ) {
			// result: add eventlist ical to rewrite rules
			flush_rewrite_rules( false );
		}
		// if iCal is disabled but rewrite rules do exist already, flush rewrite rules also
		elseif ( '1' != $this->options->get( 'el_feed_enable_ical' ) && $feed_rewrite_status ) {
			// result: remove eventlist ical from rewrite rules
			flush_rewrite_rules( false );
		}
	}


	private function sanitize_feed_text( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}


	private function get_feed_name() {
		return $this->options->get( 'el_feed_ical_name' );
	}


	public function feed_url() {
		if ( get_option( 'permalink_structure' ) ) {
			$feed_link = get_bloginfo( 'url' ) . '/feed/';
		} else {
			$feed_link = get_bloginfo( 'url' ) . '/?feed=';
		}
		return $feed_link . $this->get_feed_name();
	}

}

