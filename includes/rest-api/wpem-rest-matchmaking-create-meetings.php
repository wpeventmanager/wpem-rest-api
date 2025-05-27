<?php

class WPEM_REST_Create_Meeting_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'create-meeting';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Create Meeting
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_meeting'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id'              => ['required' => true, 'type' => 'integer'],
                'event_id'             => ['required' => true, 'type' => 'integer'],
                'meeting_date'         => ['required' => true, 'type' => 'string'],
                'meeting_start_time'   => ['required' => true, 'type' => 'string'],
                'meeting_end_time'     => ['required' => true, 'type' => 'string'],
                'meeting_participants' => ['required' => true, 'type' => 'array'],
                'write_a_message'      => ['required' => false, 'type' => 'string'],
            ],
        ]);

        // Get Meetings
        register_rest_route($this->namespace, '/get-meetings', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'get_user_meetings'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function create_meeting(WP_REST_Request $request) {
		global $wpdb;

		$user_id       = intval($request->get_param('user_id'));
		$meeting_date  = sanitize_text_field($request->get_param('meeting_date'));
		$start_time    = sanitize_text_field($request->get_param('meeting_start_time'));
		$end_time      = sanitize_text_field($request->get_param('meeting_end_time'));
		$participants  = $request->get_param('meeting_participants');
		$message       = sanitize_textarea_field($request->get_param('write_a_message'));
		$event_id      = intval($request->get_param('event_id'));

		if (!$user_id || !get_userdata($user_id)) {
			return new WP_REST_Response([
				'code' => 400,
				'status' => 'Bad Request',
				'message' => 'Invalid user.',
				'data' => []
			], 400);
		}

		if (empty($meeting_date) || empty($start_time) || empty($end_time) || empty($participants) || !is_array($participants)) {
			return new WP_REST_Response([
				'code' => 400,
				'status' => 'Bad Request',
				'message' => 'Missing or invalid parameters.',
				'data' => []
			], 400);
		}

		// Remove self from participants
		$participants = array_filter(array_map('intval', $participants), function ($pid) use ($user_id) {
			return $pid !== $user_id;
		});

		$meeting_table_name = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
		$inserted = $wpdb->insert(
			$meeting_table_name,
			[
				'user_id'            => $user_id,
				'event_id'           => $event_id,
				'participant_ids'    => implode(',', $participants),
				'meeting_date'       => $meeting_date,
				'meeting_start_time' => $start_time,
				'meeting_end_time'   => $end_time,
				'message'            => $message,
				'meeting_status'     => 0
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d']
		);

		if (!$inserted) {
			return new WP_REST_Response([
				'code' => 500,
				'status' => 'Internal Server Error',
				'message' => 'Could not create meeting. Please try again.',
				'data' => []
			], 500);
		}

		$meeting_id = $wpdb->insert_id;

		$formatted_date = date("l, d F Y", strtotime($meeting_date));
		$formatted_start_time = date("h:i A", strtotime($start_time));
		$formatted_end_time = date("h:i A", strtotime($end_time));

		$participant_details = [];
		$sender_user = get_userdata($user_id);

		if (!empty($participants)) {
			$table_name = $wpdb->prefix . 'wpem_matchmaking_users';
			$placeholders = implode(',', array_fill(0, count($participants), '%d'));
			$query = $wpdb->prepare("SELECT * FROM $table_name WHERE user_id IN ($placeholders)", ...$participants);
			$results = $wpdb->get_results($query, ARRAY_A);

			foreach ($results as $participant) {
				$user_data = get_userdata($participant['user_id']);
				$profile_picture_url = $participant['profile_photo'] ?? '';
				if (empty($profile_picture_url)) {
					$profile_picture_url = EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';
				}

				$participant_details[] = [
					'name' => $user_data ? $user_data->display_name : '',
					'profession' => $participant['profession'] ?? '',
					'profile_picture' => $profile_picture_url
				];

				// Email to participant
				$token = wp_create_nonce("meeting_action_{$meeting_id}_{$participant['user_id']}");
				$confirm_url = add_query_arg([
					'meeting_action' => 'confirm',
					'meeting_id'     => $meeting_id,
					'user_id'        => $participant['user_id'],
					'token'          => $token,
				], home_url('/'));

				$decline_url = add_query_arg([
					'meeting_action' => 'decline',
					'meeting_id'     => $meeting_id,
					'user_id'        => $participant['user_id'],
					'token'          => $token,
				], home_url('/'));

				$host_profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d", $user_id), ARRAY_A);
				$host_company = $host_profile['company_name'] ?? '';
				$host_city = $host_profile['city'] ?? '';
				$all_countries = wpem_registration_get_all_countries();
				$host_country = $all_countries[$host_profile['country']] ?? '';
				$event_name = ''; // You can pull this dynamically if needed
				$timezone_abbr = wp_timezone_string();

				$subject = "{$sender_user->display_name} requested a meeting with you";
				$body = "
					<p>Hello {$user_data->display_name},</p>
					<p><strong>{$sender_user->display_name}</strong> has requested a meeting with you.</p>
					<p><strong>Event:</strong> {$event_name}</p>
					<p><strong>Date:</strong> {$formatted_date}</p>
					<p><strong>Time:</strong> {$formatted_start_time} – {$formatted_end_time} {$timezone_abbr}</p>
					<p><strong>Company:</strong> {$host_company}<br>
					   <strong>City:</strong> {$host_city}<br>
					   <strong>Country:</strong> {$host_country}</p>
					<p><strong>Message:</strong> {$message}</p>
					<p>
						<a href='{$confirm_url}'>Confirm</a> |
						<a href='{$decline_url}'>Decline</a>
					</p>
				";

				wp_mail($user_data->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
			}
		}

		// Email to sender
		$participant_names = implode(', ', array_column($participant_details, 'name'));
		$cancel_token = wp_create_nonce("cancel_meeting_{$meeting_id}_{$user_id}");
		$cancel_url = add_query_arg([
			'meeting_action' => 'cancel',
			'meeting_id'     => $meeting_id,
			'user_id'        => $user_id,
			'token'          => $cancel_token,
		], home_url('/'));

		wp_mail(
			$sender_user->user_email,
			'Your Meeting Request Has Been Sent',
			"
			<p>Hello {$sender_user->display_name},</p>
			<p>Your meeting request has been sent to: <strong>{$participant_names}</strong>.</p>
			<p><strong>Date:</strong> {$formatted_date}</p>
			<p><strong>Time:</strong> {$formatted_start_time} - {$formatted_end_time}</p>
			<p><strong>Message:</strong> {$message}</p>
			<p><a href='{$cancel_url}'>Cancel Meeting</a></p>
			",
			['Content-Type: text/html; charset=UTF-8']
		);

		// Final Success Response
		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Meeting created and emails sent!',
			'data'    => [
				'meeting_date' => $formatted_date,
				'start_time'   => $formatted_start_time,
				'end_time'     => $formatted_end_time,
				'participants' => $participant_details,
				'message'      => $message
			]
		], 200);
	}

	public function get_user_meetings(WP_REST_Request $request) {
		global $wpdb;

		$event_id        = $request->get_param('event_id');
		$user_id         = intval($request->get_param('user_id'));
		$participants_id = $request->get_param('participants_id');

		if (!$event_id || !$user_id || empty($participants_id)) {
			return new WP_REST_Response([
				'code'    => 400,
				'status'  => 'error',
				'message' => 'Missing event_id, user_id, or participants_id.',
				'data'    => null,
			], 400);
		}

		// Normalize participants_id as array
		if (!is_array($participants_id)) {
			$participants_id = array_map('intval', explode(',', $participants_id));
		}

		// Build dynamic FIND_IN_SET conditions
		$conditions = [];
		foreach ($participants_id as $id) {
			$conditions[] = $wpdb->prepare("FIND_IN_SET(%d, participant_ids)", $id);
		}
		$where_participants = implode(" OR ", $conditions);

		// Query all matching meetings
		$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
		$sql = "
			SELECT * FROM $table
			WHERE event_id = %d
			  AND user_id = %d
			  AND ($where_participants)
		";
		$query = $wpdb->prepare($sql, $event_id, $user_id);
		$meetings = $wpdb->get_results($query, ARRAY_A);

		if (empty($meetings)) {
			return new WP_REST_Response([
				'code'    => 404,
				'status'  => 'error',
				'message' => 'No meetings found for the given input.',
				'data'    => null,
			], 404);
		}

		// Collect all unique user IDs involved (host + participants)
		$all_user_ids = [$user_id];
		foreach ($meetings as $meeting) {
			$ids = array_map('intval', explode(',', $meeting['participant_ids']));
			$all_user_ids = array_merge($all_user_ids, $ids);
		}
		$all_user_ids = array_unique($all_user_ids);

		// Fetch data from custom matchmaking user table
		$custom_user_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, profession, profile_photo FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id IN (" . implode(',', array_fill(0, count($all_user_ids), '%d')) . ")",
				...$all_user_ids
			),
			ARRAY_A
		);
		$custom_user_indexed = [];
		foreach ($custom_user_data as $u) {
			$custom_user_indexed[$u['user_id']] = $u;
		}

		// Helper to get user info
		$get_user_info = function($uid) use ($custom_user_indexed) {
			$first_name = get_user_meta($uid, 'first_name', true);
			$last_name  = get_user_meta($uid, 'last_name', true);
			return [
				'user_id'         => $uid,
				'name'            => trim("$first_name $last_name") ?: 'Unknown',
				'profession'      => $custom_user_indexed[$uid]['profession'] ?? '',
				'profile_picture' => esc_url($custom_user_indexed[$uid]['profile_photo'] ?? ''),
			];
		};

		// Final meetings list
		$meeting_data = [];

		foreach ($meetings as $meeting) {
			$participant_ids = array_map('intval', explode(',', $meeting['participant_ids']));
			$participants_info = [];

			foreach ($participant_ids as $pid) {
				if ($pid !== $user_id) {
					$participants_info[] = $get_user_info($pid);
				}
			}

			$meeting_data[] = [
				'meeting_date' => date_i18n('l, d F Y', strtotime($meeting['meeting_date'])),
				'start_time'   => date_i18n('h:i A', strtotime($meeting['start_time'])),
				'end_time'     => date_i18n('h:i A', strtotime($meeting['end_time'])),
				'message'      => $meeting['message'] ?? '',
				'host'         => $get_user_info($user_id),
				'participants' => $participants_info,
			];
		}

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Meetings retrieved successfully.',
			'data'    => $meeting_data,
		], 200);
	}
}

new WPEM_REST_Create_Meeting_Controller();
