<?= form_open($post_url); ?>

<?php
	$this->table->clear();
	$this->table->set_template($cp_table_template);
	$this->table->set_heading(array('data' => '', 'style' => "width:40%"), '');

	$this->table->add_row(
		// hidden submit button in case user presses enter, don't want it to trigger 'backup now' button
		form_submit(array('name' => 'submit', 'value' => lang('submit'), 'style' => 'width:0;height:0;border:none;margin:0;padding:0;')).
		lang('demo_site:last_restore_date'),
		empty($settings['last_restore_date']) ? lang('demo_site:never') : $this->localize->set_human_time($settings['last_restore_date'])
	);

	$this->table->add_row(
		lang('demo_site:last_restore_result'),
		nl2br($settings['last_restore_result'])
	);

	$this->table->add_row(
		lang('demo_site:next_restore_date'),
		empty($settings['last_restore_date']) ? '' : $this->localize->set_human_time($settings['next_restore_date'])
	);

	$this->table->add_row(
		lang('demo_site:backup_file'),
		form_dropdown('backup_file', $backup_file_options, $settings['backup_file']).NBS.NBS.
			form_submit('backup_now', lang('demo_site:backup_now'))
	);

	$this->table->add_row(
		lang('demo_site:restore_interval_mins'),
		form_input('restore_interval_mins', $settings['restore_interval_mins'])
	);

	echo $this->table->generate();
?>

<div style="text-align: right;">
	<?= form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit')); ?>
</div>

<?= form_close(); ?>
