<?php
class SleekPontyJob {
	private $postId;
	private static $bools = ['HIDE', 'STICKY', 'INTERNAL'];

	# Get all tags
	public static function getAllTags ($prefix = null, $pt = null) {
		$args = [
			'taxonomy' => 'pnty_job_tag',
			'hide_empty' => true
		];

		if ($pt) {
			$args['post_type'] = $pt;
		}

		$tags = self::getGroupedTags(get_terms($args));

		if ($prefix) {
			return isset($tags[$prefix]) ? $tags[$prefix] : [];
		}

		return $tags;
	}

	# Get all locations
	public static function getAllLocations ($pt = ['pnty_job', 'pnty_job_showcase']) {
		$rows = get_posts([
			'post_type' => $pt,
			'numberposts' => -1
		]);

		$locations = [];

		foreach ($rows as $row) {
			if ($location = get_post_meta($row->ID, '_pnty_location', true)) {
				$locations[$location] = $location;
			}
		}

		return $locations;
	}

	# Group list of tags based on PREFIX or bools
	private static function getGroupedTags ($terms) {
		$tags = [];

		if ($terms) {
			foreach ($terms as $term) {
				# Make sure the tag name isn't a reserved bool
				if (!in_array($term->name, self::$bools)) {
					$bits = explode(':', $term->name);

					# We have a prefixed tag (PERK:Something)
					if ($bits and count($bits) === 2) {
						$tagName = $bits[0];
						$tagValue = $bits[1];

						if (!isset($tags[$tagName])) {
							$tags[$tagName] = [];
						}

						$term->original_name = $term->name;
						$term->name = $tagValue;
						$tags[$tagName][$tagValue] = $term;
					}
					# We have a non-prefixed, non bool tag
					else {
						if (!isset($tags['CATEGORY'])) {
							$tags['CATEGORY'] = [];
						}

						$tags['CATEGORY'][$term->name] = $term;
					}
				}
			}
		}

		return $tags;
	}

	# Single job
	public function __construct ($postId) {
		$this->postId = $postId;
	}

	# Get tags for this job
	public function getTags ($prefix = null) {
		$tags = self::getGroupedTags(get_the_terms($this->postId, 'pnty_job_tag'));

		if ($prefix) {
			return isset($tags[$prefix]) ? $tags[$prefix] : [];
		}

		return $tags;
	}

	# Check if job has tag
	public function hasTag ($name) {
		return has_term($name, 'pnty_job_tag', $this->postId);
	}

	# Get custom field
	public function getField ($name) {
		return get_post_meta($this->postId, '_pnty_' . $name, true);
	}

	# Get application deadline
	public function getDeadline () {
		if ($time = $this->getField('withdrawal_date')) {
			return date_i18n(get_option('date_format'), strtotime($time));
		}

		return false;
	}

	# TODO: Is it new
	public function isNew () {

	}

	# TODO: Is it urgent
	public function isUrgent () {

	}

	# Return application URL
	public function getApplyUrl () {
		$baseUrl = 'https://pnty-apply.ponty-system.se/';
		$systemSlug = $this->getField('system_slug');
		$assignmentId = $this->getField('assignment_id');
		$externalUrl = $this->getField('external_apply_url');

		if ($externalUrl) {
			return $externalUrl;
		}
		elseif ($systemSlug and $assignmentId) {
			return $baseUrl . $systemSlug . '?id=' . $assignmentId;
		}
		else {
			return false;
		}
	}

	# Return company logo
	public function getCompanyLogo ($size = 'full') {
		# TODO: ??
		if (false and $this->getField('confidential')) {
			return false;
		}

		if (($id = $this->getField('logo_attachment_id')) and ($image = wp_get_attachment_image($id, $size))) {
			return $image;
		}

		return null;
	}

	# Get address
	public function getAddress () {
		if (($address = $this->getField('address')) and ($address = json_decode($address, true))) {
			return $address;
		}

		return null;
	}

	# Get contact persons
	public function getContactPersons () {
		$persons = [];

		if ($val = $this->getField('email')) {
			$persons[] = (object) [
				'email' => $val,
				'name' => $this->getField('name')
			];
		}

		if ($tags = $this->getTags('USER')) {
		#	$persons = []; # NOTE: If USER tags are set - ignore normal contact person

			foreach ($tags as $name => $term) {
				$persons[] = (object) [
					'email' => $name,
					'name' => null
				];
			}
		}

		foreach ($persons as $person) {
			$rows = get_posts([
				'post_type' => 'employee',
				'numberposts' => 1,
				'meta_query' => [
					[
						'key' => 'email',
						'value' => $person->email,
						'compare' => '='
					]
				]
			]);

			$person->post = $rows[0] ?? null;
		}

		return $persons;
	}
}

# Give the job CPTs archives, nice titles and URLs
add_filter('register_post_type_args', function ($args, $postType) {
	if ($postType === 'pnty_job' or $postType === 'pnty_job_showcase') {
		$args['public'] = true;
		$args['show_ui'] = true;
		$args['has_archive'] = true;
		$args['supports'] = ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'wpcom-markdown', 'trackbacks', 'custom-fields', 'revisions', 'page-attributes', 'comments'];
	}

	# Available jobs
	if ($postType === 'pnty_job') {
		$args['rewrite'] = [
			'slug' => _x('available-jobs', 'url', 'sleek'),
			'with_front' => false
		];
		$args['labels'] = [
			'name' => __('Available Jobs', 'sleek'),
			'singular_name' => __('Available Job', 'sleek')
		];
	}

	# Appointed jobs
	if ($postType === 'pnty_job_showcase') {
		$args['rewrite'] = [
			'slug' => _x('appointed-jobs', 'url', 'sleek'),
			'with_front' => false
		];
		$args['labels'] = [
			'name' => __('Appointed Jobs', 'sleek'),
			'singular_name' => __('Appointed Job', 'sleek')
		];
	}

	return $args;
}, 10, 2);

# Modify the job tag
add_filter('register_taxonomy_args', function ($args, $taxonomy) {
	if ($taxonomy == 'pnty_job_tag') {
		$args['public'] = false; # NOTE: We don't want the tags to be accessible on the frontend - they are purely used for filtering
		$args['show_ui'] = true;
		$args['show_in_rest'] = true;
		$args['rewrite'] = [
			'slug' => _x('job-tags', 'url', 'sleek'), # NOTE: Just in case we change public to true
			'with_front' => false
		];
	}

	return $args;
}, 10, 2);

# Override ponty's the_content overrides
add_filter('pnty_single_job_filter', function ($content) {
	return $content;
});

# Modify query
add_action('pre_get_posts', function ($query) {
	if (is_admin()) {
		return;
	}

	# Always ignore hidden jobs
	# (NOTE: This does not affect the singular pages so they're still accessible if you know the URL)
	$queryPostType = $query->get('post_type');
	$isJobQuery = false;

	if (
		(
			is_array($queryPostType) and
			(in_array('pnty_job', $queryPostType) or in_array('pnty_job_showcase', $queryPostType))
		) or
		(
			$queryPostType === 'any' or $queryPostType === 'pnty_job' or $queryPostType === 'pnty_job_showcase'
		)
	) {
		$taxQuery = $query->get('tax_query', ['relation' => 'AND']);
		$taxQuery[] = [
			'taxonomy' => 'pnty_job_tag',
			'field' => 'name',
			'terms' => 'HIDE',
			'operator' => 'NOT IN'
		];
		$query->set('tax_query', $taxQuery);
	}

	# Allow filtering
	if ($query->is_main_query() and (is_post_type_archive('pnty_job') or is_post_type_archive('pnty_job_showcase'))) {
		$query->set('posts_per_page', -1);

		$taxQuery = $query->get('tax_query', ['relation' => 'AND']);
		$metaQuery = $query->get('meta_query', ['relation' => 'AND']);
		$hasTaxQuery = false;
		$hasMetaQuery = false;

		if (isset($_GET['ponty_location']) and !empty(array_filter($_GET['ponty_location']))) {
			$hasMetaQuery = true;
			$metaQuery[] = [
				'key' => '_pnty_location',
				'value' => array_filter($_GET['ponty_location']),
				'compare' => 'IN'
			];
		}

		foreach ($_GET as $k => $v) {
			if (substr($k, 0, 10) === 'ponty_tag_' and !empty(array_filter($v))) {
				$hasTaxQuery = true;
				$taxQuery[] = [
					'taxonomy' => 'pnty_job_tag',
					'field' => 'term_id',
					'terms' => array_filter($v)
				];
			}
		}

		if ($hasTaxQuery) {
			$query->set('tax_query', $taxQuery);
		}
		if ($hasMetaQuery) {
			$query->set('meta_query', $metaQuery);
		}

		# See if a search string is provided
		if (isset($_GET['ponty_search'])) {
			$query->set('s', $_GET['ponty_search']);
		}
	}
}, 10, 1);

# Relevanssi search
add_filter('relevanssi_hits_filter', function ($args) {
	$rows = $args[0];

	if ($rows) {
		$rows = array_filter($rows, function ($row) {
			if (has_term('HIDE', 'pnty_job_tag', $row->ID)) {
				return false;
			}

			return true;
		});
	}

	return [$rows];
});

# Noindex hidden jobs
add_action('wp_head', function () {
	global $post;

	if (isset($post->ID) and is_singular('pnty_job') and has_term('HIDE', 'pnty_job_tag', $post->ID)) {
		echo '<meta name="robots" content="noindex,nofollow">';
	}
});
