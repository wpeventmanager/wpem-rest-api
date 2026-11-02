<?php
defined('ABSPATH') || exit;

/**
 * REST API forgot password class.
 *
 * Single endpoint: POST /wp-json/wpem/forgot-password
 *
 * Body: { "user": "username or email" }
 *   -> checks that the user exists and sends the standard WordPress
 *      password reset email (with reset link) to the user's email address.
 *      The user then resets the password from the link in that email.
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

		// throw error if user tries more than 10 times in 5 minute.
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

		// Send the standard WordPress "Password Reset" email with the reset link.
		$sent = $this->wpem_send_reset_email($user);

		if (is_wp_error($sent)) {
            return wp_send_json([
				'code'    => 500,
				'status'  => 'Error',
				'message' => $sent->get_error_message(),
			]);
		}

		if (!$sent) {
            return wp_send_json([
				'code'    => 500,
				'status'  => 'Error',
				'message' => __('The email could not be sent. Please contact the site administrator.', 'wpem-rest-api'),
			]);
		}

        return wp_send_json([
            'code'    => 200,
            'status'  => 'OK',
			'message' => __('Password reset link has been sent to your registered email address.', 'wpem-rest-api'),
		]);
	}

	/**
	 * Send the WordPress default password reset email to the user.
	 *
	 * Uses WordPress core retrieve_password(), so the mail is exactly the
	 * same as the website's "Lost your password?" form (same template,
	 * same filters, same mail settings).
	 *
	 * @param WP_User $user User object.
	 * @return bool|WP_Error True when mail was sent.
	 */
	private function wpem_send_reset_email($user)
	{
		if (!function_exists('retrieve_password')) {
			return new WP_Error(
				'wpem_retrieve_password_missing',
				__('Password reset is not supported on this WordPress version.', 'wpem-rest-api')
			);
		}

		// Older core versions read the login from $_POST.
		$previous = isset($_POST['user_login']) ? $_POST['user_login'] : null;
		$_POST['user_login'] = $user->user_login;

		$result = retrieve_password($user->user_login);

		if (null === $previous) {
			unset($_POST['user_login']);
		} else {
			$_POST['user_login'] = $previous;
		}

		return is_wp_error($result) ? $result : true;
	}
}
new WPEM_REST_Forgot_Password_Controller();