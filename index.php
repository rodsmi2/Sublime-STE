<?php
	ini_set('display_errors', 1);
	error_reporting(E_ALL);

	global $form_errors, $servers;

	$servers = array();

	/**
	 * Allows for decrypting the Dreamweaver STE password
	 * 
	 * @param  string $password
	 * @return string
	 */
	function decrypt_ste_password( $password = "" ) {
		$output = "";
		$password = (string) $password;

		if( empty( $password ) ) return $output;

		// Thanks to http://andrewtrivette.com/2013/01/dreamweaver-key-extraction/
		$hex = str_split( $password, 2 );

		foreach ( $hex as $key => $value ) {
		    $output .= chr( hexdec( $value ) - $key );
		}

		return $output;
	}

	/**
	 * Creates the Sublime Text sftp-config.json file
	 * to allow to connect to the server
	 * 
	 * @param  array  $server
	 */
	function generate_sublime_file( $server = array() ) {
		global $form_errors;

		if( !isset( $server['host'] ) || !isset( $server['username'] ) || !isset( $server['password'] ) || !isset( $server['folder'] ) ) {
			$form_errors[] = 'Not all required server information was given to create the config file.';
			return FALSE;
		}

		ob_start(); ?>
{
    // The tab key will cycle through the settings when first created
    // Visit http://wbond.net/sublime_packages/sftp/settings for help
    
    // sftp, ftp or ftps
    "type": "{{FTP-SETTING}}",

    "save_before_upload": true,
    "upload_on_save": false,
    "sync_down_on_open": false,
    "sync_skip_deletes": false,
    "confirm_downloads": false,
    "confirm_sync": true,
    "confirm_overwrite_newer": false,
    
    "host": "{{FTP-HOST}}",
    "user": "{{FTP-USER}}",
    "password": "{{FTP-PASSWORD}}",
    //"port": "22",
    
    "remote_path": "{{FTP-PATH}}",
    "ignore_regexes": [
        "\\.sublime-(project|workspace)", "sftp-config(-alt\\d?)?\\.json",
        "sftp-settings\\.json", "/venv/", "\\.svn", "\\.hg", "\\.git",
        "\\.bzr", "_darcs", "CVS", "\\.DS_Store", "Thumbs\\.db", "desktop\\.ini"
    ],
    //"file_permissions": "664",
    //"dir_permissions": "775",
    
    //"extra_list_connections": 0,

    "connect_timeout": 30,
    //"keepalive": 120,
    //"ftp_passive_mode": true,
    //"ssh_key_file": "~/.ssh/id_rsa",
    //"sftp_flags": ["-F", "/path/to/ssh_config"],
    
    //"preserve_modification_times": false,
    //"remote_time_offset_in_hours": 0,
    //"remote_encoding": "utf-8",
    //"remote_locale": "C",
}
		<?php
		$json_string = ob_get_clean();

		// Add in server info (TODO: add SFTP options)
		$json_string = str_replace( '{{FTP-SETTING}}', 'ftp', $json_string );
		$json_string = str_replace( '{{FTP-HOST}}', $server['host'], $json_string );
		$json_string = str_replace( '{{FTP-USER}}', $server['username'], $json_string );
		$json_string = str_replace( '{{FTP-PASSWORD}}', $server['password'], $json_string );
		$json_string = str_replace( '{{FTP-PATH}}', (  substr( $server['folder'], 0, 1 ) != '/' ? '/' . $server['folder'] : $server['folder'] ), $json_string );

		$file_name = 'sftp-config.json';
	    header( 'Content-Type: application/octet-stream' );
	    header( 'Content-Transfer-Encoding: Binary' );
	    header( 'Content-disposition: attachment; filename="' . $file_name . '"' );

	    echo $json_string;
	    exit();
	}

	/**
	 * Generates plain text file with server credentials
	 * if generic FTP info is needed without Sublime
	 * integration
	 * 
	 * @param  array  $server
	 */
	function generate_text_file( $server = array() ) {
		global $form_errors;

		if( !isset( $server['host'] ) || !isset( $server['username'] ) || !isset( $server['password'] ) || !isset( $server['folder'] ) ) {
			$form_errors[] = 'Not all required server information was given to create the file.';
			return FALSE;
		}

		ob_start(); ?>
SERVER CONNECTION INFO
----------------------
TYPE: {{FTP-SETTING}}
HOST: {{FTP-HOST}}
USERNAME: {{FTP-USER}}
PASSWORD: {{FTP-PASSWORD}}
PATH: {{FTP-PATH}}
		<?php
		$text_string = ob_get_clean();

		// Add in server info (TODO: add SFTP options)
		$text_string = str_replace( '{{FTP-SETTING}}', 'ftp', $text_string );
		$text_string = str_replace( '{{FTP-HOST}}', $server['host'], $text_string );
		$text_string = str_replace( '{{FTP-USER}}', $server['username'], $text_string );
		$text_string = str_replace( '{{FTP-PASSWORD}}', $server['password'], $text_string );
		$text_string = str_replace( '{{FTP-PATH}}', (  substr( $server['folder'], 0, 1 ) != '/' ? '/' . $server['folder'] : $server['folder'] ), $text_string );

		$file_name = 'server-connection.txt';
	    header( 'Content-Type: application/octet-stream' );
	    header( 'Content-Transfer-Encoding: Binary' );
	    header( 'Content-disposition: attachment; filename="' . $file_name . '"' );

	    echo $text_string;
	    exit();

	}

	/**
	 * Handles Processing form submissions
	 */
	function process_form() {
		if( $_FILES['ste_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file( $_FILES['ste_file']['tmp_name'] ) ) {
			$ste_xml_string = file_get_contents( $_FILES['ste_file']['tmp_name'] );
			$ste_xml_string = preg_replace('#&(?=[A-Za-z_0-9]+)#', '&amp;', $ste_xml_string);
		} else {
			// TODO : ERROR
			exit( 'bad file' );
		}

		$ste_xml = simplexml_load_string( $ste_xml_string );

		// New Dreamweaver
		if( isset( $ste_xml->serverlist ) ) {
			foreach( $ste_xml->serverlist->server as $server ) {

				$attributes = $server->attributes();

				$servers[] = array(
					'name' => urldecode($attributes['name']),
					'host' => $attributes['host'],
					'folder' => $attributes['remoteroot'],
					'username' => $attributes['user'],
					'password' => decrypt_ste_password($attributes['pw'])
				);
			}
		}

		// Old Dreamweaver
		if( isset( $ste_xml->remoteinfo ) ) {
			foreach( $ste_xml->remoteinfo as $server ) {
				$attributes = $server->attributes();

				$servers[] = array(
					'name' => $attributes['accesstype'],
					'host' => $attributes['host'],
					'folder' => $attributes['remoteroot'],
					'username' => $attributes['user'],
					'password' => decrypt_ste_password( $attributes['pw'] )
				);
			}
		}
	}

	if( !empty($_POST) ) {
		if( !empty($_FILES) ) {
			process_form();
		}
	}
?>
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <title>Sublime STE</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width">

        <!-- Place favicon.ico and apple-touch-icon.png in the root directory -->

        <link rel="stylesheet" href="css/normalize.css">
        <link rel="stylesheet" href="css/main.css">
        <script src="js/vendor/modernizr-2.6.2.min.js"></script>
    </head>
    <body>
        <!--[if lt IE 7]>
            <p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
        <![endif]-->

        <form action="" method="post" enctype="multipart/form-data">
			<input type="hidden" name="posting" value="true" />
			<label for="ste_file">STE File:</label>
			<input type="file" name="ste_file" />

			<input type="submit" value="submit" />
		</form>
    </body>
</html>