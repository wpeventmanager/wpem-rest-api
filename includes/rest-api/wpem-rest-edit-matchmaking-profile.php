<?php 

defined('ABSPATH') || exit;

/**
 * REST API Attendee Profile Update controller class.
 */
class WPEM_REST_Attendee_Profile_Update_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'attendee-profile';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/update',
            array(
                'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH/POST
                'callback' => array($this, 'update_attendee_profile'),
                'permission_callback' => '__return_true', // For testing, allow all (you should restrict this in production)
                'args' => array(
                    'user_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                ),
            )
        );
    }

    public function update_attendee_profile($request) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpem_matchmaking_users';
		$user_id = $request->get_param('user_id');

		$user = get_user_by('id', $user_id);
		if (!$user) {
			return new WP_REST_Response(array(
				'code' => 404,
				'status' => 'Not Found',
				'message' => 'User not found.',
				'data' => null
			), 404);
		}

		$existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
		if (!$existing) {
			return new WP_REST_Response(array(
				'code' => 404,
				'status' => 'Not Found',
				'message' => 'Attendee profile not found in matchmaking table.',
				'data' => null
			), 404);
		}

		$custom_fields = array(
			'profile_photo',
			'profession',
			'experience',
			'company_name',
			'country',
			'city',
			'about',
			'skills',
			'interests',
			'organization_name',
			'organization_logo',
			'organization_city',
			'organization_country',
			'organization_description',
			'message_notification',
			'approve_profile_status',
		);

		$custom_data = [];
		foreach ($custom_fields as $field) {
			if ($request->get_param($field) !== null) {
				$custom_data[$field] = sanitize_text_field($request->get_param($field));
			}
		}

		if (!empty($custom_data)) {
			$updated = $wpdb->update($table, $custom_data, array('user_id' => $user_id));
			if ($updated === false) {
				return new WP_REST_Response(array(
					'code' => 500,
					'status' => 'Error',
					'message' => 'Failed to update matchmaking profile.',
					'data' => null
				), 500);
			}
		}

		$first_name = $request->get_param('first_name');
		$last_name = $request->get_param('last_name');
		$email = $request->get_param('email');

		if ($first_name !== null) {
			update_user_meta($user_id, 'first_name', sanitize_text_field($first_name));
		}

		if ($last_name !== null) {
			update_user_meta($user_id, 'last_name', sanitize_text_field($last_name));
		}

		if ($email !== null) {
			$email = sanitize_email($email);
			$email_exists = email_exists($email);

			if ($email_exists && $email_exists != $user_id) {
				return new WP_REST_Response(array(
					'code' => 400,
					'status' => 'Error',
					'message' => 'Email address already in use by another user.',
					'data' => null
				), 400);
			}

			$email_update = wp_update_user(array(
				'ID' => $user_id,
				'user_email' => $email,
			));

			if (is_wp_error($email_update)) {
				return new WP_REST_Response(array(
					'code' => 500,
					'status' => 'Error',
					'message' => 'Failed to update email.',
					'data' => $email_update->get_error_message()
				), 500);
			}
		}

		return new WP_REST_Response(array(
			'code' => 200,
			'status' => 'OK',
			'message' => 'Request is successfully completed.',
		), 200);
	}

}

new WPEM_REST_Attendee_Profile_Update_Controller();


?>