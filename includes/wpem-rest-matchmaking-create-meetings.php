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

        register_rest_route($this->namespace, '/update-meeting-status', [
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'update_meeting_status'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'meeting_id' => ['required' => true, 'type' => 'integer'],
				'user_id'    => ['required' => true, 'type' => 'integer'],
				'status'     => ['required' => true, 'type' => 'integer', 'enum' => [0, 1]],
			],
		]);
		register_rest_route($this->namespace, '/get-availability-slots', [
			'methods'  => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_booked_meeting_slots'],
			'permission_callback' => [$auth_controller, 'check_authentication'],
			'args' => [
				'event_id' => [
					'required' => true,
					'type' => 'integer'
				],
				'user_id' => [
					'required' => false,
					'type' => 'integer'
				]
			]
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
		$participants = array_fill_keys($participants, -1);  

        $table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
        $inserted = $wpdb->insert($table, [
            'user_id'            => $user_id,
            'event_id'           => $event_id,
            'participant_ids'    => serialize($participants),
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
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}

		global $wpdb;

		$event_id = intval($request->get_param('event_id'));
		$user_id  = intval($request->get_param('user_id'));

		if (!$event_id || !$user_id) {
			return new WP_REST_Response([
				'code' => 400,
				'status' => 'error',
				'message' => 'Missing event_id or user_id.',
				'data' => null
			], 400);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';

		// Fetch ALL meetings for this event
		$all_meetings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE event_id = %d", $event_id), ARRAY_A);

		if (empty($all_meetings)) {
			return new WP_REST_Response([
				'code' => 404,
				'status' => 'error',
				'message' => 'No meetings found for this event.',
				'data' => null
			], 404);
		}

		$meeting_data = [];

		foreach ($all_meetings as $meeting) {
			$participant_statuses = maybe_unserialize($meeting['participant_ids']);
			if (!is_array($participant_statuses)) {
				$participant_statuses = [];
			}

			// Check if current user is host or in participant list
			if ((int)$meeting['user_id'] !== $user_id && !array_key_exists($user_id, $participant_statuses)) {
				continue;
			}

			$participants_info = [];
			foreach ($participant_statuses as $pid => $status) {
				//if ((int)$pid === $user_id) continue;

				$user_data = get_userdata($pid);
				$display_name = $user_data ? $user_data->display_name : '';

				$meta = $wpdb->get_row($wpdb->prepare(
					"SELECT profile_photo, profession, company_name FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d",
					$pid
				));

				$participants_info[] = [
					'id'         => (int)$pid,
					'status'     => (int)$status,
					'name'       => $display_name,
					'image'      => !empty($meta->profile_photo) ? esc_url($meta->profile_photo) : '',
					'profession' => isset($meta->profession) ? esc_html($meta->profession) : '',
					'company'    => isset($meta->company_name) ? esc_html($meta->company_name) : '',
				];
			}
			$host_id = (int)$meeting['user_id'];
			$host_user = get_userdata($host_id);
			$host_meta = $wpdb->get_row($wpdb->prepare(
				"SELECT profile_photo, profession, company_name FROM {$wpdb->prefix}wpem_matchmaking_users WHERE user_id = %d",
				$host_id
			));

			$host_info = [
				'id'         => $host_id,
				'name'       => $host_user ? $host_user->display_name : '',
				'image'      => !empty($host_meta->profile_photo) ? esc_url($host_meta->profile_photo) : '',
				'profession' => isset($host_meta->profession) ? esc_html($host_meta->profession) : '',
				'company'    => isset($host_meta->company_name) ? esc_html($host_meta->company_name) : '',
			];
			$meeting_data[] = [
				'meeting_id'     => (int)$meeting['id'],
				'meeting_date'   => date_i18n('l, d F Y', strtotime($meeting['meeting_date'])),
				'start_time'     => date_i18n('h:i A', strtotime($meeting['meeting_start_time'])),
				'end_time'       => date_i18n('h:i A', strtotime($meeting['meeting_end_time'])),
				'message'        => $meeting['message'],
				'host_info'      => $host_info,
				'participants'   => $participants_info,
				'meeting_status' => (int)$meeting['meeting_status']
			];
		}

		if (empty($meeting_data)) {
			return new WP_REST_Response([
				'code' => 404,
				'status' => 'error',
				'message' => 'No meetings found for this user.',
				'data' => null
			], 404);
		}

		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Meetings retrieved successfully.',
			'data'    => $meeting_data
		], 200);
	}

	public function cancel_meeting(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}
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
    public function update_meeting_status(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}
		global $wpdb;

		$meeting_id = intval($request->get_param('meeting_id'));
		$user_id    = intval($request->get_param('user_id'));
		$new_status = intval($request->get_param('status'));

		if (!$meeting_id || !$user_id || !in_array($new_status, [0, 1], true)) {
			return new WP_REST_Response(['code' => 400, 'message' => 'Invalid parameters.'], 400);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users_meetings';
		$meeting = $wpdb->get_row($wpdb->prepare("SELECT participant_ids FROM $table WHERE id = %d", $meeting_id));

		if (!$meeting) {
			return new WP_REST_Response(['code' => 404, 'message' => 'Meeting not found.'], 404);
		}

		$participant_data = maybe_unserialize($meeting->participant_ids);
		if (!is_array($participant_data)) {
			$participant_data = [];
		}

		if (!array_key_exists($user_id, $participant_data)) {
			return new WP_REST_Response(['code' => 403, 'message' => 'You are not a participant of this meeting.'], 403);
		}

		// Update current user's status
		$participant_data[$user_id] = $new_status;

		// If at least one participant has accepted (status = 1), mark meeting as accepted
		$meeting_status = (in_array(1, $participant_data, true)) ? 1 : 0;

		// Update in DB
		$updated = $wpdb->update(
			$table,
			[
				'participant_ids' => maybe_serialize($participant_data),
				'meeting_status'  => $meeting_status,
			],
			['id' => $meeting_id],
			['%s', '%d'],
			['%d']
		);

		if ($updated === false) {
			return new WP_REST_Response(['code' => 500, 'message' => 'Failed to update status.'], 500);
		}

		return new WP_REST_Response([
			'code' => 200,
			'status' => 'OK',
			'message' => $new_status ? 'Meeting accepted.' : 'Meeting declined.',
			'data' => [
				'meeting_id'       => $meeting_id,
				'participant_id'   => $user_id,
				'participant_status' => $new_status,
				'meeting_status'   => $meeting_status,
			]
		], 200);
	}
	public function get_booked_meeting_slots(WP_REST_Request $request) {
		if (!get_option('enable_matchmaking', false)) {
			return new WP_REST_Response([
				'code' => 403,
				'status' => 'Disabled',
				'message' => 'Matchmaking functionality is not enabled.',
				'data' => null
			], 403);
		}
		global $wpdb;

		$event_id = intval($request->get_param('event_id'));
		$user_id  = intval($request->get_param('user_id')) ?: get_current_user_id();

		if (!$event_id || !$user_id) {
			return new WP_REST_Response([
				'code' => 400,
				'status' => 'error',
				'message' => 'Missing event_id or unauthorized access.',
				'data' => null
			], 400);
		}

		$table = $wpdb->prefix . 'wpem_matchmaking_users';
		$saved = $wpdb->get_var(
			$wpdb->prepare("SELECT meeting_availability_slot FROM $table WHERE user_id = %d", $user_id)
		);

		$saved_data = !empty($saved) ? maybe_unserialize($saved) : [];

		$event_slots = $saved_data[$event_id] ?? [];
		
		if (is_array($event_slots)) {
			ksort($event_slots); // sorts date keys in ascending order
		}
		return new WP_REST_Response([
			'code'    => 200,
			'status'  => 'OK',
			'message' => 'Availability slots fetched successfully.',
			'data'    => $event_slots
		], 200);
	}
}

new WPEM_REST_Create_Meeting_Controller();
