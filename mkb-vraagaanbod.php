<?php
/*
Plugin Name: MKB Vraag/Aanbod Plugin
Plugin URI: http://www.emendo-it.nl
Description: Plugin to allow users to post requests that are only available for limited time
Version: 0.1
Author: Bas Grolleman <bgrolleman@emendo-it.nl>
License: No License 
*/
register_activation_hook(__FILE__,'mkbvraagaanbod_install');
add_shortcode('mkbvraagaanbod','mkbvraagaanbod_main');
add_action('admin_menu', 'mkbvraagaanbod_add_page');

$editor_role = get_role('editor'); $editor_role->add_cap('mkb_vraagaanbod');
$admin_role = get_role('administrator'); $admin_role->add_cap('mkb_vraagaanbod');
//add_settings_field('duration','Duur advertentie', 'mkbvraagaanbod_duration', 'general');

function mkbvraagaanbod_install() {
	global $wpdb;
	$mkbvraagaanbod_table = $wpdb->prefix . "mkbvraagaanbod";
	$sql = "CREATE TABLE $mkbvraagaanbod_table (
		id bigint(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id bigint(20) unsigned,
		subject varchar(255),
		description varchar(8096),
		request_type enum('vraag','aanbod'),
    created_on datetime,
		expires_on datetime,
		modified_on datetime
	);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}


function mkbvraagaanbod_print_form( $id = '', $subject = '', $description = '', $request_type = '' ) {
?>
	<form>
		<?php if ( $id ) { ?><input type="hidden" name="mkb_vraagaanbod_id" value="<?= $id ?>" /><?php } ?>
		<label><?= __('Titel') ?></label>
  		<input size="60" type="text" name="mkb_vraagaanbod_subject" value="<?= $subject ?>" />
		<label><?= __('Soort') ?></label>
			<input type="radio" name="mkb_vraagaanbod_type" value="vraag" <?php if ( $request_type=='vraag' ) { print "checked"; } ?> /> <?= __('Vraag') ?> 
			<input type="radio" name="mkb_vraagaanbod_type" value="aanbod" <?php if ( $request_type=='aanbod' ) { print "checked"; } ?> /> <?= __('Aanbod') ?> 
		<label><?= __('Omschrijving') ?></label>
  		<textarea rows="10" name="mkb_vraagaanbod_description"><?= $description ?></textarea>
		<?php
			if ( $id ) {
				print '<input type="submit" name="mkb_vraagaanbod_submit" value="' . __('Aanpassen') . '" />';
			} else {
				print '<input type="submit" name="mkb_vraagaanbod_submit" value="' . __('Toevoegen') . '" />';
			}
		?>
	</form>
<?php
}

function mkbvraagaanbod_button( $name, $title ) {
	return '<input type="submit" name="' . $name . '" value="' . __($title) . '" />';
}


function mkbvraagaanbod_main() {
	// Main Function - Handle all requests
	global $wpdb;

	// Set a few variables
	$current_user = wp_get_current_user();
	$mkbvraagaanbod_table = $wpdb->prefix . "mkbvraagaanbod";

	// Users without this power shouldn't add stuff
	if ( current_user_can('mkb_vraagaanbod')) {
		if ( $_REQUEST['mkb_vraagaanbod_submit'] ) {
			if ( $_REQUEST['mkb_vraagaanbod_id'] ) {
				if ( $wpdb->query($wpdb->prepare("
					UPDATE $mkbvraagaanbod_table SET
						subject = %s,
						description = %s,
						request_type = %s,
						modified_on = NOW()
					WHERE
 						user_id = %d and
						id = %d",
						strip_tags($_REQUEST["mkb_vraagaanbod_subject"]),strip_tags($_REQUEST["mkb_vraagaanbod_description"]),$_REQUEST["mkb_vraagaanbod_type"],$current_user->id,$_REQUEST["mkb_vraagaanbod_id"]
					))) {
						print "<div id='message' class='info'><p>" . strip_tags($_REQUEST['mkb_vraagaanbod_subject']) . ' ' . __('Bijgewerkt!') . '</p></div>';
					} else {
						print "<div id='message' class='info'><p>" . strip_tags($_REQUEST['mkb_vraagaanbod_subject']) . ' ' . __('opslaan mislukt!') . '</p></div>';
					}
			}
			if ( ! $_REQUEST['mkb_vraagaanbod_id']) {
				$row = $wpdb->get_var($wpdb->prepare("select count(*) from $mkbvraagaanbod_table where user_id = %d and subject = %s", $current_user->id, $_REQUEST['mkb_vraagaanbod_subject'] ));
				if ( $row > 0 ) {
					print "<div id='message' class='info'><p>" . __('Item bestaat al') . '</p></div>';
					return "";
				};
				if ( $wpdb->query($wpdb->prepare(" 
					INSERT INTO " . $wpdb->prefix . "mkbvraagaanbod
						( user_id, subject, description, request_type, created_on, expires_on, modified_on )
						VALUES
						( %d, %s, %s, %s, NOW(), NOW() + INTERVAL 2 WEEK, NOW() );",
							$current_user->id, strip_tags($_REQUEST['mkb_vraagaanbod_subject']),strip_tags($_REQUEST['mkb_vraagaanbod_description']),$_REQUEST['mkb_vraagaanbod_type']
				))) {
					print __('Vraag en aanbod toegevoegd!');
				} else {
					print __('Vraag en aanbod mislukt!');
				}
			}
		};
		if ( $_REQUEST['mkb_vraagaanbod_add'] ) { 
			mkbvraagaanbod_print_form();
			return "";
		} else {
			print '<form>' . mkbvraagaanbod_button('mkb_vraagaanbod_add','Vraag/Aanbod Toevoegen') . '</form>';
		}
	}

	// Now, I don't care who you are, if you want to edit something attached to your user ID, you get to edit
	if ( $_REQUEST['mkb_vraagaanbod_id'] ) {
		$rows = $wpdb->get_results("select * from $mkbvraagaanbod_table where id = " . $_REQUEST['mkb_vraagaanbod_id']);
		if ( $rows ) {
			$item = $rows[0];
			if (( $current_user->id == $item->user_id) or ( $current_user->role == 'administrator' ))	{
				if ( $_REQUEST['mkb_vraagaanbod_edit'] ) {
					print "Your edit form here!";
					// Do something
					mkbvraagaanbod_print_form($item->id, $item->subject, $item->description, $item->request_type);
					return "";
				}
				if ( $_REQUEST['mkb_vraagaanbod_remove'] ) {
					// Remove Something
					$wpdb->query('delete from ' . $mkbvraagaanbod_table . ' where id = ' . $item->id);
					print "<div id='message' class='info'><p>" . $item->subject . ' verwijderd!</p></div>';
				}
			}
		}
	}
	//
	// This is a simple plugin, so we just fetch all active rows and show them to the end user
	$rows = $wpdb->get_results("
		select id, user_id, subject, description, UNIX_TIMESTAMP(created_on) as created_on, UNIX_TIMESTAMP(expires_on) as expires_on, UNIX_TIMESTAMP(modified_on) as modified_on
		from $mkbvraagaanbod_table
		where
			expires_on >= NOW()
		order by
			modified_on DESC;");
	foreach($rows as $row) {
		// This isn't very nice for the DB, might want to rewrite that later
		$user = get_user_by('id',$row->user_id);
		// Link to user page is not very clean, but couldn't find the clean solution quick enough
		$result = $result . '
			<div class="mkbvraagaanbodentry">
				<div class="mkbvraagaanbod_subject"><label>' . __('Subject') . ':</label>' . $row->subject . '</div>
				<div class="mkbvraagaanbod_user"><a title="' . __('Bekijk profiel van') . ' ' . $user->display_name . '" href="/members/' . $user->user_login . '"><div>' . get_avatar($user->id) . '</div><div style="clear:both">' . $user->display_name . '</a><br/><a href="mailto:' . $user->user_email . '">' . __('Stuur email') . '</a></div></div>
				<div class="mkbvraagaanbod_description"><label>' . __('Description') . ':</label>' . nl2br($row->description) . '</div>
				<div class="mkbvraagaanbod_meta">' . __('Loopt tot') . ' ' . date('l, F j, Y',$row->expires_on) . '</div>';
		if (( $current_user->id == $row->user_id ) or ( $current_user->role == 'adminstrator' )) {
			$result = $result . '
				<form>
					<input type="hidden" name="mkb_vraagaanbod_id" value="'. $row->id . '" />
					<input type="submit" name="mkb_vraagaanbod_edit" value="Aanpassen" />
					<input type="submit" name="mkb_vraagaanbod_remove" value="Verwijderen" />
				</form>
			';
		}
		$result = $result . '</div>';
	}
	return $result;
}

function mkbvraagaanbod_add_page() {
	add_options_page('MKBVraagAanbod Page','MKB Vraag/Aanbod', 'manage_options', 'mkbvraagaanbod', 'mkbvraagaanbod_options_page');
}

function mkbvraagaanbod_options_page() {
	?>
	<div>
		<h2>Opties voor MKB Eemland Vraag/Aanbod Plugin</h2>
		<p>Add duration options see this http://ottopress.com/2009/wordpress-settings-api-tutorial/</p>
		<form action="options.php" method="post">
			<?php settings_fields('plugin_options'); ?>
			<?php do_settings_sections('plugin'); ?>
			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
		</form>
	</div>
	<?php
}
?>
