<?php
	/**
	 * Plugin Name: Lil Contact Form
	 * Plugin URI: http://www.perihelion.org/
	 * Description: A super simple contact form with built-in anti-spam features. No settings to adjust, simply add the shortcode [lil_contact] to any page or post to include a contact form.
	 * Version: 1.2
	 * Author: Wyatt Kirby
	 * Author URI: http://wyattkirby.com/
	 **/

	/**
	 * Plugin Class Definition
	 */

	if(!class_exists('PN_Contact_Form')) {
		class PN_Contact_Form {

			private $to = '';

			/**
			 * Build Plugin
			 */
			
			public function __construct() {
				add_action('init', array(&$this, 'plugin_init'));
				add_shortcode('lil_contact', array(&$this, 'contact_form_shortcode'));
			}

			public function plugin_init() {
				if (!session_id()) { session_start(); }
				$_SESSION['pn_email_sent'] = false;
				$_SESSION['pn_contact_errors'] = array();

				// Be Polite about JS
				add_action( 'wp_enqueue_scripts', array(&$this, 'plugin_scripts') );
			}

			public function register_scripts() {
				
			}

			public function plugin_scripts() {
				if(self::has_shortcode('lil_contact')) {
					wp_enqueue_script('jquery');
					wp_enqueue_script('jquery-validation', plugins_url('/js/jquery.validation.js', __FILE__ ), array('jquery'), '1.2', true);
					wp_enqueue_script('jquery-custom', plugins_url('/js/jquery.lil-contact.min.js', __FILE__ ), array('jquery', 'jquery-validation'), '1.2', true);
				}
			}

			public function contact_form_shortcode($attributes) {
				extract( shortcode_atts( array(
						'to' => get_bloginfo( 'admin_email' )
					), $attributes ) );
				
				// Set proper person to send to
				$this->to = $to;

				$html = '<div class="pn_contact_form">';
					$html .= $_SERVER['REQUEST_METHOD'] == 'POST' ? self::process_contact_form() : null;
					$html .= self::build_form_html();
				$html .= '</div>';

				return $html;
			}

			private function build_form_html() {
				$contact_form_url = get_permalink();
				$contact_name     = empty($_POST['contact_name']) ? null : $_POST['contact_name'];
				$contact_email    = empty($_POST['contact_email']) ? null : $_POST['contact_email'];
				$contact_message  = empty($_POST['contact_message']) ? null : $_POST['contact_message'];


				$contact_form = "<form action='$contact_form_url' method='POST'>";
					$contact_form .= sprintf('<div class="form-group">%s</div>', self::build_input('contact_name', 'Name', 'text', $contact_name));
					$contact_form .= sprintf('<div class="form-group">%s</div>', self::build_input('contact_email', 'Email', 'email', $contact_email));
					$contact_form .= sprintf('<div class="form-group">%s</div>', self::build_textarea('contact_message', 'Message', $contact_message));
					
					$contact_form .= self::build_input('contact_start', null, 'hidden', date("Y-m-d H:i:s"), false);
					$contact_form .= self::build_input('fightthematrix', null, 'hidden', null, false);
					
					$contact_form .= '<input type="submit" class="btn btn-default" />';
				$contact_form .= '</form>';


				return $contact_form;
			}

			private function process_contact_form() {
				// Avoid Spam
				$bots = "/(Indy|Blaiz|Java|libwww-perl|Python|OutfoxBot|User-Agent|PycURL|AlphaServer)/i";
				if ( preg_match( $bots, $_SERVER['HTTP_USER_AGENT'] ) ) {
					exit( __( 'Known spam bots are not allowed.', 'pn_framework' ) );
				}

				// Cleanup
				foreach ( $_POST as $k => $v ) {
					$_POST[$k] = trim( htmlspecialchars( strip_tags( stripslashes( $_POST[$k] ) ) ) );
				}

				// Check for Errors
				$_SESSION['pn_contact_errors'] = array();
				!empty($_POST['fightthematrix'])    ? $_SESSION['pn_contact_errors'][] = __('Argh! Robots!', 'pn_framework') : false;
				empty($_POST['contact_name'])       ? $_SESSION['pn_contact_errors'][] = __('Please include your name.', 'pn_framework') : false;
				!is_email($_POST['contact_email'])  ? $_SESSION['pn_contact_errors'][] = __('Please include a valid email.', 'pn_framework') : false;
				empty($_POST['contact_message'])    ? $_SESSION['pn_contact_errors'][] = __('Please include a message.', 'pn_framework') : false;
				strtotime(date("Y-m-d H:i:s")) - strtotime($_POST['contact_start']) < 2 ? $_SESSION['pn_contact_errors'][] = __('Slow down buddy!', 'pn_framework') : false;

				// Send email if valid
				if(count($_SESSION['pn_contact_errors']) < 1 && $_SESSION['pn_email_sent'] === false) {
					self::send_message($_POST['contact_name'], $_POST['contact_email'], $_POST['contact_message']);
				}

				// Display result
				return self::display_result();
			}

			private function send_message($name, $email, $message) {

				$headers     = "From: $name <$email> \r\n";
				$headers    .= "MIME-Version: 1.0 \r\n";
				$headers    .= "Content-type: text/html; charset=iso-8859-1 \r\n";
				
				$subject     = sprintf( "%s: %s", get_bloginfo('name'), __("Contact Form", "pn_framework") );

				$email_body  = sprintf( "<p><strong>%s:</strong> %s</p>", __( "Name", "pn_framework" ), $name );
				$email_body .= sprintf( "<p><strong>%s:</strong> %s</p><hr/>", __( "Email", "pn_framework" ), $email );
				$email_body .= sprintf( "<p>%s</p><hr/>", $message );
				$email_body .= sprintf( "<p>Sent from %s at %s.</p>", $_SERVER['REMOTE_ADDR'], date('H:i:s d/m/Y') );

				if(mail( $this->to, $subject, $email_body, $headers )) {
					$_SESSION['pn_email_sent'] = true;
					$_SESSION['pn_contact_errors'] = array();
				} else {
					$_SESSION['pn_contact_errors'][] = __('Delivery failed, please try again in just a moment.', 'pn_framework');
				}
			}

			private function display_result() {
				$success = sprintf('<div class="panel panel-success"><div class="panel-heading">%s</div></div>', __("Email Sent", "pn_framework"));
				$errors = sprintf('<div class="panel panel-danger"><div class="panel-heading">%s</div><div class="panel-body">%s</div></div>', __("Errors", "pn_framework"), join(', ', $_SESSION['pn_contact_errors']));
				return $_SESSION['pn_email_sent'] ? $success : $errors;
			}

			private function build_input($input_id, $input_label, $input_type, $input_val, $required = 'required') {
				$label = sprintf('<label class="control-label" for="%1$s">%2$s</label>', $input_id, $input_label);
				$input = sprintf('<input id="%1$s" name="%1$s" type="%2$s" value="%3$s" class="form-control" %4$s/>', $input_id, $input_type, $input_val, $required);
				return isset($input_label) ?  $label . $input : $input;
			}

			private function build_textarea($input_id, $input_label, $input_val, $required = 'required') {
				$label = sprintf('<label class="control-label" for="%1$s">%2$s</label>', $input_id, $input_label);
				$input = sprintf('<textarea id="%1$s" name="%1$s" class="form-control" rows="8" %3$s>%2$s</textarea>', $input_id, $input_val, $required);
				return isset($input_label) ?  $label . $input : $input;
			}

			private function has_shortcode($shortcode = null) {
				if ($shortcode) {
					global $wp_query;	
				    
				    foreach ($wp_query->posts as $post){
						if (   preg_match_all( '/'. get_shortcode_regex() .'/s', $post->post_content, $matches )
							&& array_key_exists( 2, $matches )
							&& in_array( $shortcode, $matches[2] ) )
						{
							return true;
						}    
				    }
				}

				return false;
			}
		}
	}

	/**
	 * Instantiate Plugin
	 */
	
	if(class_exists('PN_Contact_Form')) {
		$pn_contact_form = new PN_Contact_Form();
	}
?>