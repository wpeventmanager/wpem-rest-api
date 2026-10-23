<?php
defined('ABSPATH') || exit;

/**
 * REST API forgot password class.
 *
 * Single endpoint: POST /wp-json/wpem/forgot-password
 *
 * Type 1 body: { "user": "username or email" }
 *   -> checks that the user exists
 *
 * Type 2 body: { "user": "username or email", "password": "new password" }
 *   -> checks that the user exists and updates the password in wp_users
 *
 * @since 1.4.5
 */
class WPEM_REST_Forgot_Password_Controller extends WPEM_REST_CRUD_Controller
{
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wpem';

    /**
     * Route base for profile endpoints.
     *
     * @var string
     */
    protected $rest_base = 'forgot-password';

	/**
	 * Initialize.
	 */
	public function __construct()
	{
		add_action('rest_api_init', array($this, 'wpem_register_routes'), 10);
	}

	/**
	 * Register the route.
	 */
	public function wpem_register_routes()
	{
		register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'wpem_forgot_password'),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Basic throttle per IP using transients.
	 *
	 * @param int $max    Max requests in the window.
	 * @param int $window Window in seconds.
	 * @return bool True when allowed.
	 */
	private function wpem_throttle($max, $window)
	{
		$ip        = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
		$transient = 'wpem_fp_' . md5($ip);
		$count     = (int) get_transient($transient);
		if ($count >= $max) {
			return false;
		}
		set_transient($transient, $count + 1, $window);
		return true;
	}

	/**
	 * Handle the forgot password request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function wpem_forgot_password($request)
	{
		if (!(int) get_option('enable_wpem_rest_api')) {
            return wp_send_json([
				'code'    => 507,
				'status'  => 'REST API Disabled',
				'message' => __('REST API feature is disabled.', 'wpem-rest-api'),
			]);
		}

		if (!$this->wpem_throttle(10, 5 * MINUTE_IN_SECONDS)) {
            return wp_send_json([
				'code'    => 429,
				'status'  => 'Too many requests',
				'message' => __('Too many requests. Please try again after some time.', 'wpem-rest-api'),
			]);
		}

		$params     = $request->get_json_params();
		$params     = is_array($params) ? $params : array();
		$identifier = isset($params['user']) && is_string($params['user']) ? trim(sanitize_text_field(wp_unslash($params['user']))) : '';

		if ('' === $identifier) {
            return wp_send_json([
				'code'    => 400,
				'status'  => 'Bad request',
				'message' => __('Please enter your username or email address.', 'wpem-rest-api'),
			]);
		}

		// Find user by email (if it looks like one), otherwise by username.
		$user = false;
		if (is_email($identifier)) {
			$user = get_user_by('email', $identifier);
		}
		if (!$user) {
			$user = get_user_by('login', $identifier);
		}
		if (!$user) {
            return wp_send_json([
				'code'    => 404,
				'status'  => 'Not found',
				'message' => __('User not found.', 'wpem-rest-api'),
			]);
		}

		// Type 1: only "user" passed -> just confirm the user exists.
		if (!array_key_exists('password', $params)) {
            return wp_send_json([
				'code'    => 200,
				'status'  => 'OK',
				'message' => __('User found.', 'wpem-rest-api'),
			]);
		}

		// Type 2: "user" + "password" -> update the password.
		// Do not sanitize the password; it must be saved exactly as typed.
		$password = is_string($params['password']) ? wp_unslash($params['password']) : '';
		if ('' === $password) {
            return wp_send_json([
				'code'    => 400,
				'status'  => 'Bad request',
				'message' => __('Please enter a new password.', 'wpem-rest-api'),
			]);
		}

		// Hashes the password like every other WordPress user and saves it in wp_users.user_pass.
		wp_set_password($password, $user->ID);

		// Log the user out of existing sessions.
		WP_Session_Tokens::get_instance($user->ID)->destroy_all();

        return wp_send_json([
            'code'    => 200,
            'status'  => 'OK',
            'message' => __('Password has been updated successfully.', 'wpem-rest-api'),
        ]);
	}
}
new WPEM_REST_Forgot_Password_Controller();