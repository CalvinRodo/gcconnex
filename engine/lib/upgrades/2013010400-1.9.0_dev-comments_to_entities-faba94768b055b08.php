<?php
/**
 * Elgg 1.9.0-dev upgrade 2013010400
 * comments_to_entities
 *
 * Convert comment annotations to entities
 */

// Register subtype and class for comments
if (get_subtype_id('object', 'comment')) {
	update_subtype('object', 'comment', 'ElggComment');
} else {
	add_subtype('object', 'comment', 'ElggComment');
}

$ia = elgg_set_ignore_access(true);

// upgrade latest 50 comments from annotations.
$batch = new ElggBatch('elgg_get_annotations', array(
	'annotation_names' => 'generic_comment',
	'order_by' => 'n_table.id DESC',
	'limit' => 50,
));
$batch->setIncrementOffset(false);

$db_prefix = elgg_get_config('dbprefix');

// Create a new object for each annotation
foreach ($batch as $annotation) {
	/* @var ElggAnnotation $annotation */

	$object = new ElggComment();
	$object->owner_guid = $annotation->owner_guid;
	$object->container_guid = $annotation->entity_guid;
	$object->description = $annotation->value;
	$object->access_id = $annotation->access_id;
	$object->time_created = $annotation->time_created;
	$object->save();

	$guid = $object->getGUID();

	/**
	 * Update the entry in river table for this comment
	 *
	 * - Update the view path
	 * - Remove annotation id
	 * - Save comment guid to the target_guid column
	 */
	$query = "
		UPDATE {$db_prefix}river
		SET view = 'river/object/comment/create',
			type = 'object',
			subtype = 'comment',
			annotation_id = 0,
			object_guid = $guid,
			target_guid = {$object->container_guid}
		WHERE action_type = 'comment'
		  AND annotation_id = {$annotation->id}
	";

	update_data($query);

	// Delete the annotation
	$annotation->delete();
}

elgg_set_ignore_access($ia);


$migrate_link = elgg_view('output/url', array(
	'href' => 'admin/comment_upgrade',
	'text' => "migrate the rest of the comments",
	'is_trusted' => true,
));

// not using translation because new keys won't be in the cache
elgg_add_admin_notice('comment_upgrade_needed', "The data structure of site comments has changed in Elgg 1.9. The most recent 50 comments were migrated but you must $migrate_link.");
