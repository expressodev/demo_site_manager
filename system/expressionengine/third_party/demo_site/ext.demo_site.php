<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Exp:Store module for ExpressionEngine 2.x by Crescendo (support@crescendo.net.nz)
 * Copyright (c) 2012 Crescendo Multimedia Ltd
 * All rights reserved.
 */

class Demo_site_ext
{
	public $name = 'Demo Site Manager';
	public $description = 'Easily restore your EE demo site on a schedule.';
	public $version = '1.0';
	public $docs_url = 'http://exp-resso.com';
	public $settings_exist = 'y';
	public $settings;
	public $backup_dir;
	public $bin_paths = array('/bin', '/usr/bin', '/usr/local/bin');

	public function __construct($settings = array())
	{
		$this->EE =& get_instance();

		$this->backup_dir = PATH_THIRD.'demo_site/backups';

		// initialize settings
		$this->init_settings($settings);
	}

	public function settings()
	{
		return array(
			'restore_interval_mins' => '60',
			'backup_file' => '',
			'last_restore_date' => '',
			'last_restore_result' => '',
		);
	}

	public function init_settings($settings)
	{
		foreach ($this->settings() as $key => $default)
		{
			// load default setting if necessary
			if ( ! isset($this->settings[$key]))
			{
				$this->settings[$key] = $default;
			}

			// load custom setting
			if (isset($settings[$key]))
			{
				$this->settings[$key] = $settings[$key];
			}
		}
	}

	public function activate_extension()
	{
		$this->EE->db->insert('extensions', array(
			'class'		=> 'Demo_site_ext',
			'method'	=> 'sessions_start',
			'hook'		=> 'sessions_start',
			'priority'	=> '10',
			'version'	=> $this->version,
			'enabled'	=> 'y'
		));

		return TRUE;
	}

	public function update_extension($current = '')
	{
		$this->EE->db->where('class', 'Demo_site_ext')
			->update('extensions', array('version' => $this->version));

		return TRUE;
	}

	public function settings_form($settings)
	{
		$this->init_settings($settings);

		$data = array(
			'settings' => $this->settings,
			'post_url' => 'C=addons_extensions&amp;M=extension_settings&amp;file=demo_site',
			'backup_file_options' => array('' => lang('demo_site:none')),
		);

		foreach (scandir($this->backup_dir) as $file)
		{
			if (stripos($file, '.sql.gz'))
			{
				$data['backup_file_options'][$file] = $file;
			}
		}

		$data['settings']['next_restore_date'] = $this->next_restore_date();

		if ( ! empty($_POST))
		{
			if ($this->EE->input->post('backup_now'))
			{
				// do it
				if ( ! is_dir($this->backup_dir) AND ! mkdir($this->backup_dir))
				{
					show_error(lang('demo_site:unable_to_create_dir'));
				}

				$this->settings['backup_file'] = $this->do_backup();
				$this->settings['last_restore_date'] = $this->EE->localize->now;
			}
			elseif ($this->EE->input->post('submit'))
			{
				// save backup date
				$this->settings['restore_interval_mins'] = (int)$this->EE->input->post('restore_interval_mins');
			}

			// save settings and redirect
			$this->EE->db->where('class', __CLASS__)
				->update('extensions', array('settings' => serialize($this->settings)));

			$this->EE->session->set_flashdata(
				'message_success',
				$this->EE->lang->line('preferences_updated')
			);

			$this->EE->functions->redirect(BASE.AMP.$data['post_url']);
		}

		return $this->EE->load->view('settings', $data, TRUE);
	}

	/**
	 * Add product information to channel entries tags
	 */
	public function sessions_start(&$session)
	{
		// don't do a restore on POST queries
		if ( ! empty($_POST)) return;

		$next_restore_date = $this->next_restore_date();
		if (empty($next_restore_date)) return;

		if ($next_restore_date < $this->EE->localize->now)
		{
			$this->EE->session =& $session;
			$result = $this->do_restore();

			// update our settings in the new database
			$this->settings['last_restore_date'] = $this->EE->localize->now;
			$this->settings['last_restore_result'] = $result;

			$this->EE->db->where('class', __CLASS__)
				->update('extensions', array('settings' => serialize($this->settings)));

			// refresh page
			$this->EE->functions->redirect($this->EE->input->server('REQUEST_URI'));
		}
	}

	protected function next_restore_date()
	{
		if (empty($this->settings['backup_file']) OR
			empty($this->settings['last_restore_date']) OR
			$this->settings['restore_interval_mins'] < 1)
		{
			return 0;
		}

		return (int)$this->settings['last_restore_date'] + ((int)$this->settings['restore_interval_mins'] * 60);
	}

	protected function do_backup()
	{
		$db = $this->db();
		$filename = $db->database.'-'.date("Y-m-d-H-i-s").'.sql.gz';
		$filepath = $this->backup_dir.'/'.$filename;

		$mysqldump = $this->path_to('mysqldump');
		$gzip = $this->path_to('gzip');

		$cmd = "$mysqldump --opt -h $db->hostname -u$db->username -p$db->password $db->database | $gzip > $filepath";
		$output = $return_val = NULL;
		exec($cmd, $output, $return_val);

		// check for errors
		if ( ! empty($return_val))
		{
			show_error(lang('demo_site:unable_to_backup').BR.$cmd.BR.implode(BR, $output));
		}

		return $filename;
	}

	protected function db()
	{
		return (object)array(
			'hostname' => strtolower($this->EE->db->hostname) == 'localhost' ? '127.0.0.1' : $this->EE->db->hostname,
			'database' => $this->EE->db->database,
			'username' => $this->EE->db->username,
			'password' => $this->EE->db->password,
		);
	}

	protected function path_to($binary)
	{
		foreach ($this->bin_paths as $path)
		{
			if (file_exists($path.'/'.$binary))
			{
				return $path.'/'.$binary;
			}
		}

		return $binary;
	}

	protected function do_restore()
	{
		// ensure backup file exists
		$filepath = $this->backup_dir.'/'.$this->settings['backup_file'];
		if ( ! is_readable($filepath)) return "Can't read file: $filepath";

		// restore backup file
		$db = $this->db();
		$mysql = $this->path_to('mysql');
		$gunzip = $this->path_to('gunzip');
		$cmd = "$gunzip < $filepath | $mysql -h $db->hostname -u$db->username -p$db->password $db->database";

		$output = $return_val = NULL;
		exec($cmd, $output, $return_val);

		return implode("\n", $output);
	}
}

/* End of file ext.demo_site.php */