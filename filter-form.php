<?php
	$tags = SleekPontyJob::getAllTags();
	$locations = SleekPontyJob::getAllLocations();
	$tags = apply_filters('sleek_ponty_tags', $tags);
?>

<form method="get" action="" id="ponty-filter-form" data-submit-onchange>

	<p class="input--search">
		<input type="text" name="ponty_search" placeholder="<?php _e('Search job', 'sleek') ?>" value="<?php echo $_GET['ponty_search'] ?? '' ?>">
	</p>

	<?php if ($locations) : ?>
		<p class="select locations">
			<select name="ponty_location[]">
				<option value=""><?php _ex('LOCATION', 'ponty-tag', 'sleek') ?></option>
				<?php foreach ($locations as $location) : ?>
					<option value="<?php echo $location ?>" <?php echo (isset($_GET['ponty_location']) and in_array($location, $_GET['ponty_location'])) ? 'selected' : '' ?>>
						<?php echo $location ?>
					</option>
				<?php endforeach ?>
			</select>
		</p>
	<?php endif ?>

	<?php foreach ($tags as $tag => $terms) : if (count($terms) > 3) : ?>
		<p class="select <?php echo sanitize_title($tag) ?>">
			<select name="ponty_tag_<?php echo strtolower($tag) ?>[]">
				<option value=""><?php _ex($tag, 'ponty-tag', 'sleek') ?></option>
				<?php foreach ($terms as $term) : ?>
					<option value="<?php echo $term->term_id ?>" <?php echo (isset($_GET['ponty_tag_' . strtolower($tag)]) and in_array($term->term_id, $_GET['ponty_tag_' . strtolower($tag)])) ? 'selected' : '' ?>>
						<?php echo $term->name ?>
					</option>
				<?php endforeach ?>
			</select>
		</p>
	<?php endif; endforeach ?>

	<?php foreach ($tags as $tag => $terms) : if (count($terms) < 4) : ?>
		<p class="checkbox <?php echo sanitize_title($tag) ?>">
			<strong><?php _ex($tag, 'ponty-tag', 'sleek') ?></strong>
			<?php foreach ($terms as $term) : ?>
				<label>
					<input type="checkbox" name="ponty_tag_<?php echo strtolower($tag) ?>[]" value="<?php echo $term->term_id ?>" <?php echo (isset($_GET['ponty_tag_' . strtolower($tag)]) and in_array($term->term_id, $_GET['ponty_tag_' . strtolower($tag)])) ? 'checked' : '' ?>>
					<?php echo $term->name ?>
				</label>
			<?php endforeach ?>
		</p>
	<?php endif; endforeach ?>

	<p class="submit"><button><?php _e('Search', 'sleek') ?></button></p>

</form>
