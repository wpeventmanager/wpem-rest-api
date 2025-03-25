<?php
defined('ABSPATH') || exit;

/**
 * REST API Matchmaking Menu controller class.
 */
class WPEM_REST_Matchmaking_Menu_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'matchmaking-menu';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'), 10);
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_filtered_attendees'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'filter' => array('required' => true, 'type' => 'string'),
                    'search' => array('required' => false, 'type' => 'string'),
                    'country' => array('required' => false, 'type' => 'string'),
                    'experience' => array('required' => false, 'type' => 'number'),
                    'city' => array('required' => false, 'type' => 'string'),
                    'companyName' => array('required' => false, 'type' => 'string'),
                    'skills' => array('required' => false, 'type' => 'string'),
                    'interests' => array('required' => false, 'type' => 'string'),
                    'page' => array('required' => false, 'type' => 'number', 'default' => 1),
                    'limit' => array('required' => false, 'type' => 'number', 'default' => 10),
                    'userId' => array('required' => false, 'type' => 'integer')
                ),
            )
        );
    }

    public function get_filtered_attendees($request) {
        $filter = $request->get_param('filter');
        $search = sanitize_text_field($request->get_param('search'));
        $country = sanitize_text_field($request->get_param('country'));
        $experience = $request->get_param('experience');
        $city = sanitize_text_field($request->get_param('city'));
        $company_name = sanitize_text_field($request->get_param('companyName'));
        $skills = sanitize_text_field($request->get_param('skills'));
        $interests = sanitize_text_field($request->get_param('interests'));
        $page = $request->get_param('page');
        $limit = $request->get_param('limit');
        $user_id = $request->get_param('userId');

        $offset = ($page - 1) * $limit;

        $args = array(
            'number' => $limit,
            'offset' => $offset,
            'meta_query' => array('relation' => 'AND')
        );

        if (!empty($country)) {
            $args['meta_query'][] = array(
                'key' => 'country',
                'value' => $country,
                'compare' => '='
            );
        }

        if (!empty($experience)) {
            $args['meta_query'][] = array(
                'key' => 'experience',
                'value' => $experience,
                'compare' => '='
            );
        }

        if (!empty($city)) {
            $args['meta_query'][] = array(
                'key' => 'city',
                'value' => $city,
                'compare' => '='
            );
        }

        if (!empty($company_name)) {
            $args['meta_query'][] = array(
                'key' => 'company_name',
                'value' => $company_name,
                'compare' => '='
            );
        }

        if (!empty($skills)) {
            $args['meta_query'][] = array(
                'key'     => 'skills',
                'value'   => '"' . $skills . '"',
                'compare' => 'LIKE'
            );
        }

        if (!empty($interests)) {
            $args['meta_query'][] = array(
                'key'     => 'interests',
                'value'   => '"' . $interests . '"',
                'compare' => 'LIKE'
            );
        }

        $users = get_users($args);
        $results = [];

        foreach ($users as $user) {
            $skills_data = get_user_meta($user->ID, 'skills', true);
            $interests_data = get_user_meta($user->ID, 'interests', true);

            $user_data = array(
                'attendeeId' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'photo' => get_avatar_url($user->ID),
                'occupation' => get_user_meta($user->ID, 'occupation', true),
                'experience' => get_user_meta($user->ID, 'experience', true),
                'companyName' => get_user_meta($user->ID, 'company_name', true),
                'country' => get_user_meta($user->ID, 'country', true),
                'city' => get_user_meta($user->ID, 'city', true),
                'about' => get_user_meta($user->ID, 'about', true),
                'skills' => is_array($skills_data) ? $skills_data : explode(',', $skills_data),
                'interests' => is_array($interests_data) ? $interests_data : explode(',', $interests_data),
            );

            if ($search) {
                $haystack = strtolower($user_data['name'] . $user_data['occupation'] . implode(',', $user_data['skills']) . implode(',', $user_data['interests']));
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }

            if ($filter === 'match' && $user_id) {
                $current_skills = explode(',', get_user_meta($user_id, 'skills', true));
                $current_interests = explode(',', get_user_meta($user_id, 'interests', true));
                $current_occupation = get_user_meta($user_id, 'occupation', true);

                $match = false;

                if (in_array($user_data['occupation'], $current_skills)) {
                    $match = true;
                }

                $user_skills = $user_data['skills'];
                $user_interests = $user_data['interests'];

                if (array_intersect($current_skills, $user_skills) || array_intersect($current_interests, $user_interests)) {
                    $match = true;
                }

                if (!$match) {
                    continue;
                }
            }

            $results[] = $user_data;
        }

        if (empty($results)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No user found with your match.',
                'data' => []
            ), 200);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Attendees retrieved successfully.',
            'data' => $results
        ), 200);
    }
}

new WPEM_REST_Matchmaking_Menu_Controller();