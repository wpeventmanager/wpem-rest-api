<?php 
class WPEM_REST_Filter_Users_Controller {

    protected $namespace = 'wpem';
    protected $rest_base = 'filter-users';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_filter_users'),
            'permission_callback' => '__return_true',
            'args' => array(
                'profession'    => array('required' => false, 'type' => 'string'),
				'company_name'  => array('required' => false, 'type' => 'string'),
				'country'       => array('required' => false, 'type' => 'array'),
				'city'          => array('required' => false, 'type' => 'string'),
				'experience'    => array('required' => false, 'type' => 'integer'), // or 'array' if you want range
				'skills'        => array('required' => false, 'type' => 'array'),
				'interests'     => array('required' => false, 'type' => 'array'),
            ),
        ));
    }

   public function handle_filter_users($request) {
    global $wpdb;

    if (!get_option('enable_matchmaking', false)) {
        return new WP_REST_Response([
            'code'    => 403,
            'status'  => 'Disabled',
            'message' => 'Matchmaking functionality is not enabled.',
            'data'    => null
        ], 403);
    }

    $table = $wpdb->prefix . 'wpem_matchmaking_users';
    $where_clauses = [];
    $query_params = [];

    // Profession
    if ($profession = sanitize_text_field($request->get_param('profession'))) {
        $where_clauses[] = "profession = %s";
        $query_params[] = $profession;
    }

    // Experience (support exact or range)
    $experience = $request->get_param('experience');
    if (is_array($experience) && isset($experience['min'], $experience['max'])) {
        $where_clauses[] = "experience BETWEEN %d AND %d";
        $query_params[] = (int) $experience['min'];
        $query_params[] = (int) $experience['max'];
    } elseif (is_numeric($experience)) {
        $where_clauses[] = "experience = %d";
        $query_params[] = (int) $experience;
    }

    // Company name
    if ($company = sanitize_text_field($request->get_param('company_name'))) {
        $where_clauses[] = "company_name = %s";
        $query_params[] = $company;
    }

    // Country (string or array)
    $countries = $request->get_param('country');
    if (is_array($countries)) {
        $placeholders = implode(',', array_fill(0, count($countries), '%s'));
        $where_clauses[] = "country IN ($placeholders)";
        $query_params = array_merge($query_params, $countries);
    } elseif (!empty($countries)) {
        $country = sanitize_text_field($countries);
        $where_clauses[] = "country = %s";
        $query_params[] = $country;
    }

    // City
    if ($city = sanitize_text_field($request->get_param('city'))) {
        $where_clauses[] = "city = %s";
        $query_params[] = $city;
    }

    // Skills (serialized array match using LIKE)
    $skills = $request->get_param('skills');
    if (is_array($skills) && !empty($skills)) {
        foreach ($skills as $skill) {
            $like = '%' . $wpdb->esc_like(serialize($skill)) . '%';
            $where_clauses[] = "skills LIKE %s";
            $query_params[] = $like;
        }
    }

    // Interests (serialized array match using LIKE)
    $interests = $request->get_param('interests');
    if (is_array($interests) && !empty($interests)) {
        foreach ($interests as $interest) {
            $like = '%' . $wpdb->esc_like(serialize($interest)) . '%';
            $where_clauses[] = "interests LIKE %s";
            $query_params[] = $like;
        }
    }

    // Only include approved profiles if required
    if (get_option('participant_activation') === 'manual') {
        $where_clauses[] = "approve_profile_status = '1'";
    }

    $where_sql = implode(' AND ', $where_clauses);
    $sql = "SELECT * FROM $table WHERE $where_sql";

    $prepared_sql = $wpdb->prepare($sql, $query_params);
    $results = $wpdb->get_results($prepared_sql, ARRAY_A);

    return new WP_REST_Response([
        'code'    => 200,
        'status'  => 'OK',
        'message' => 'Users retrieved successfully.',
        'data'    => $results
    ], 200);
}

}

new WPEM_REST_Filter_Users_Controller();

?>