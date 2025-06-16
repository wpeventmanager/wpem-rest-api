<?php

class WPEM_REST_Create_Meeting_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'create-meeting';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $auth_controller = new WPEM_REST_Authentication();

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_meeting'],
            'permission_callback' => [$auth_controller, 'check_authentication'],
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

        register_rest_route($this->namespace, '/get-meetings', [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'get_user_meetings'],
            'permission_callback' => [$auth_controller, 'check_authentication'],
        ]);
		register_rest_route($this->namespace, '/cancel-meeting', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'cancel_meeting'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
			],
		]);
    }

    public function create_meeting(WP_REST_Request $request) {
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response(['code' => 403, 'status' => 'Disabled', 'message' => 'Matchmaking functionality is not enabled.', 'data' => null], 403);
        }

        global $wpdb;

        $user_id      = intval($request->get_param('user_id'));
        $event_id     = intval($request->get_param('event_id'));
        $meeting_date = sanitize_text_field($request->get_param('meeting_date'));
        $start_time   = sanitize_text_field($request->get_param('meeting_start_time'));
        $end_time     = sanitize_text_field($request->get_param('meeting_end_time'));
        $participants = $request->get_param('meeting_participants');
        $message      = sanitize_textarea_field($request->get_param('write_a_message'));

        if (!$user_id || !get_userdata($user_id) || empty($meeting_date) || empty($start_time) || empty($end_time) || empty($participants) || !is_array($participants)) {
            return new WP_REST_Response(['code' => 400, 'status' => 'Bad Request', 'message' => 'Missing or invalid parameters.', 'data' => []], 400);
        }

        // Filter out the user themselves from participant list
        $participants = array_filter(array_map('intval', $participants), fn($pid) => $pid !== $user_id);

        $table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
        $inserted = $wpdb->insert($table, [
            'user_id'            => $user_id,
            'event_id'           => $event_id,
            'participant_ids'    => implode(',', $participants),
            'meeting_date'       => $meeting_date,
            'meeting_start_time' => $start_time,
            'meeting_end_time'   => $end_time,
            'message'            => $message,
            'meeting_status'     => 0
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d']);

        if (!$inserted) {
            return new WP_REST_Response(['code' => 500, 'status' => 'Internal Server Error', 'message' => 'Could not create meeting.', 'data' => []], 500);
        }

        $meeting_id = $wpdb->insert_id;
        $formatted_date = date("l, d F Y", strtotime($meeting_date));
        $formatted_start_time = date("h:i A", strtotime($start_time));
        $formatted_end_time = date("h:i A", strtotime($end_time));
        $sender_user = get_userdata($user_id);

        $participant_details = [];

        if (!empty($participants)) {
            $profile_table = $wpdb->prefix . 'wpem_matchmaking_users';
            $placeholders = implode(',', array_fill(0, count($participants), '%d'));
            $query = $wpdb->prepare("SELECT * FROM $profile_table WHERE user_id IN ($placeholders)", ...$participants);
            $results = $wpdb->get_results($query, ARRAY_A);

            foreach ($results as $participant) {
                $participant_user = get_userdata($participant['user_id']);
                $participant_name = $participant_user->display_name ?? 'User';
                $participant_email = $participant_user->user_email ?? '';
                $profile_picture = $participant['profile_photo'] ?: EVENT_MANAGER_REGISTRATIONS_PLUGIN_URL . '/assets/images/user-profile-photo.png';

                $participant_details[] = [
                    'name'            => $participant_name,
                    'profession'      => $participant['profession'] ?? '',
                    'profile_picture' => esc_url($profile_picture)
                ];

                // Email to participant
                $host_profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d", $user_id), ARRAY_A);
                $host_company = $host_profile['company_name'] ?? '';
                $host_city    = $host_profile['city'] ?? '';
                $all_countries = wpem_registration_get_all_countries();
                $host_country = $all_countries[$host_profile['country']] ?? '';
                $event_name = get_the_title($event_id) ?: '';
                $timezone_abbr = wp_timezone_string();

                $subject = "{$sender_user->display_name} requested a meeting with you";
                $body = "
                    <p>Hello {$participant_name},</p>
                    <p><strong>{$sender_user->display_name}</strong> has requested a meeting with you.</p>
                    <p><strong>Event:</strong> {$event_name}</p>
                    <p><strong>Date:</strong> {$formatted_date}</p>
                    <p><strong>Time:</strong> {$formatted_start_time} – {$formatted_end_time} {$timezone_abbr}</p>
                    <p><strong>Company:</strong> {$host_company}<br>
                       <strong>City:</strong> {$host_city}<br>
                       <strong>Country:</strong> {$host_country}</p>
                    <p><strong>Message:</strong> {$message}</p>
                ";

                wp_mail($participant_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
            }
        }

        // Email to sender
        $participant_names = implode(', ', array_column($participant_details, 'name'));
        wp_mail(
            $sender_user->user_email,
            'Your Meeting Request Has Been Sent',
            "
            <p>Hello {$sender_user->display_name},</p>
            <p>Your meeting request has been sent to: <strong>{$participant_names}</strong>.</p>
            <p><strong>Date:</strong> {$formatted_date}</p>
            <p><strong>Time:</strong> {$formatted_start_time} - {$formatted_end_time}</p>
            <p><strong>Message:</strong> {$message}</p>
            ",
            ['Content-Type: text/html; charset=UTF-8']
        );

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
        if (!get_option('enable_matchmaking', false)) {
            return new WP_REST_Response(['code' => 403, 'status' => 'Disabled', 'message' => 'Matchmaking functionality is not enabled.', 'data' => null], 403);
        }

        global $wpdb;

        $event_id        = intval($request->get_param('event_id'));
        $user_id         = intval($request->get_param('user_id'));
        $participants_id = $request->get_param('participants_id');

        if (!$event_id || !$user_id || empty($participants_id)) {
            return new WP_REST_Response(['code' => 400, 'status' => 'error', 'message' => 'Missing event_id, user_id, or participants_id.', 'data' => null], 400);
        }

        $participants_id = is_array($participants_id) ? array_map('intval', $participants_id) : array_map('intval', explode(',', $participants_id));

        $conditions = [];
        foreach ($participants_id as $id) {
            $conditions[] = $wpdb->prepare("FIND_IN_SET(%d, participant_ids)", $id);
        }
        $where_participants = implode(" OR ", $conditions);

        $table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
        $sql = "
            SELECT * FROM $table
            WHERE event_id = %d
              AND (user_id = %d OR ($where_participants))
        ";
        $query = $wpdb->prepare($sql, $event_id, $user_id);
        $meetings = $wpdb->get_results($query, ARRAY_A);

        if (empty($meetings)) {
            return new WP_REST_Response(['code' => 404, 'status' => 'error', 'message' => 'No meetings found.', 'data' => null], 404);
        }

        // Collect all user IDs involved
        $all_user_ids = [$user_id];
        foreach ($meetings as $meeting) {
            $all_user_ids = array_merge($all_user_ids, array_map('intval', explode(',', $meeting['participant_ids'])));
        }
        $all_user_ids = array_unique($all_user_ids);

        $profiles = $wpdb->get_results(
            $wpdb->prepare("SELECT user_id, profession, profile_photo FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id IN (" . implode(',', array_fill(0, count($all_user_ids), '%d')) . ")", ...$all_user_ids),
            ARRAY_A
        );
        $custom_user_indexed = array_column($profiles, null, 'user_id');

        $meeting_data = [];
        foreach ($meetings as $meeting) {
            $participant_array = maybe_unserialize($meeting['participant_ids']);
			$participants_info = [];

			if (is_array($participant_array)) {
				foreach ($participant_array as $pid => $status) {
					if ((int)$pid !== $user_id) {
						$participants_info[] = [
							'id'     => (int)$pid,
							'status' => (int)$status,
						];
					}
				}
			}

			$meeting_data[] = [
				'meeting_id'           => (int)$meeting['id'],
				'meeting_date' => date_i18n('l, d F Y', strtotime($meeting['meeting_date'])),
				'start_time'   => date_i18n('h:i A', strtotime($meeting['meeting_start_time'])),
				'end_time'     => date_i18n('h:i A', strtotime($meeting['meeting_end_time'])),
				'message'      => $meeting['message'],
				'host'         => (int)$meeting['user_id'],
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
	public function cancel_meeting(WP_REST_Request $request) {
		global $wpdb;

		$meeting_id = intval($request->get_param('meeting_id'));
		$user_id    = intval($request->get_param('user_id'));

		if (!$meeting_id || !$user_id) {
			return new WP_REST_Response(['code' => 400, 'status' => 'error', 'message' => 'Missing meeting_id or user_id.'], 400);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
		$meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $meeting_id), ARRAY_A);

		if (!$meeting) {
			return new WP_REST_Response(['code' => 404, 'status' => 'error', 'message' => 'Meeting not found.'], 404);
		}

		if ((int)$meeting['user_id'] !== $user_id) {
			return new WP_REST_Response(['code' => 403, 'status' => 'error', 'message' => 'Only the host can cancel the meeting.'], 403);
		}

		$updated = $wpdb->update($table, ['meeting_status' => -1], ['id' => $meeting_id], ['%d'], ['%d']);

		if (!$updated) {
			return new WP_REST_Response(['code' => 500, 'status' => 'error', 'message' => 'Failed to cancel meeting.'], 500);
		}

		// Notify participants
		$participant_ids = maybe_unserialize($meeting['participant_ids']);
		if (!is_array($participant_ids)) {
			$participant_ids = [];
		}

		foreach ($participant_ids as $pid => $status) {
			$user = get_userdata($pid);
			if ($user) {
				wp_mail(
					$user->user_email,
					'Meeting Cancelled',
					"<p>Hello {$user->display_name},</p>
					<p>The meeting scheduled on <strong>{$meeting['meeting_date']}</strong> has been <strong>cancelled</strong>.</p>",
					['Content-Type: text/html; charset=UTF-8']
				);
			}
		}

		// Notify host
		$host = get_userdata($user_id);
		if ($host) {
			wp_mail(
				$host->user_email,
				'You Cancelled a Meeting',
				"<p>Hello {$host->display_name},</p>
				<p>You have cancelled the meeting scheduled on <strong>{$meeting['meeting_date']}</strong>.</p>",
				['Content-Type: text/html; charset=UTF-8']
			);
		}

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Meeting cancelled successfully.',
			'data'    => ['meeting_id' => $meeting_id],
		], 200);
	}
}

new WPEM_REST_Create_Meeting_Controller();
