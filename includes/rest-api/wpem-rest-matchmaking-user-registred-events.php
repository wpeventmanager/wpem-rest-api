<?php

class WPEM_REST_User_Registered_Events_Controller {
    protected $namespace = 'wpem';
    protected $rest_base = 'user-registered-events';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_user_registered_events'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'user_id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                        'description' => 'User ID to fetch registered events for.'
                    )
                )
            )
        );
    }

    public function get_user_registered_events($request) {
        $target_user_id = intval($request->get_param('user_id'));

        $args = array(
            'post_type'      => 'event_registration',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_attendee_user_id',
                    'value' => $target_user_id,
                )
            ),
        );

        $query = new WP_Query($args);
        $event_ids = array();

        foreach ($query->posts as $registration_id) {
            $event_id = get_post_meta($registration_id, '_event_id', true);
            if (!empty($event_id) && !in_array($event_id, $event_ids)) {
                $event_ids[] = (int) $event_id;
            }
        }

        if (empty($event_ids)) {
            return new WP_REST_Response(array(
                'code'    => 200,
                'status'  => 'OK',
                'message' => 'No registered events found.',
                'data'    => []
            ), 200);
        }

        $events = array();
        foreach ($event_ids as $event_id) {
            $post = get_post($event_id);
            if ($post && $post->post_type === 'event_listing') {
                $events[] = array(
                    'event_id'    => $event_id,
                    'title'       => get_the_title($event_id),
                    'status'      => $post->post_status,
                    'start_date'  => get_post_meta($event_id, '_event_start_date', true),
                    'end_date'    => get_post_meta($event_id, '_event_end_date', true),
                    'location'    => get_post_meta($event_id, '_event_location', true),
                );
            }
        }

        return new WP_REST_Response(array(
            'code'    => 200,
            'status'  => 'OK',
            'message' => 'Events retrieved successfully.',
            'data'    => $events
        ), 200);
    }
}

new WPEM_REST_User_Registered_Events_Controller();
