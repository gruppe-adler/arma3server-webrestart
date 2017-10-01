<?php

const SERVER_TEMPLATE_ROOT = '/home/arma3server/arma3/config/templates';
const RESTART_TRIGGER_FILENAME = '/tmp/arma3server-webrestart-command';
const ARMA3_PATH = '/home/arma3server/arma3';

$secretsFile = '/etc/arma3server-webrestart.ini';
$secrets = parse_ini_file($secretsFile);
if (!$secrets) {
	echo 'MISSING SECRETS FILE OR SECRETS FILE INVALID. EXAMPLE FOR VALID STRUCTURE: nomisum=s3cr3t';
}


header("Content-Type: text/html;charset=UTF-8");


function send($statuscode, $message = '', $output = '')
{
	if (!$message) {
		switch ($statuscode) {
			case 200:
				$message = 'OK';
				break;
			case 500:
				$message = 'Server error';
				break;
			default:
				$message = 'wtf';
		}
	}
	if (!$output) {
		$output = $message;
	}
	header("HTTP/1.1 $statuscode $message");

	echo $output;
	exit;
}


function triggerRestart($user, $port, $template = '')
{
	$date = date('Y-m-d H:i:s');
	file_put_contents(
		RESTART_TRIGGER_FILENAME,
		json_encode([
			'date' => $date,
			'user' => $user,
			'port' => $port,
			'template' => $template
		]) . "\n",
		FILE_APPEND
	);
	chmod(RESTART_TRIGGER_FILENAME, 0666);
}

function getTemplateNames() {
	if (!is_dir(SERVER_TEMPLATE_ROOT)) {
		throw new RuntimeException('SERVER_TEMPLATE_ROOT must be a directory');
	}

	$is_dir = function ($filename) {
		return is_dir(SERVER_TEMPLATE_ROOT . '/' . $filename) && !is_link(SERVER_TEMPLATE_ROOT . '/' . $filename);
	};

	$is_not_dot = function ($dirname) {
		return strpos($dirname, '.') !== 0;
	};

	$is_not_dangerous = function ($dirname) {
		return strpos($dirname, '..') === false;
	};

	$entries = scandir(SERVER_TEMPLATE_ROOT);
	$entries = array_filter($entries, $is_dir);
	$entries = array_filter($entries, $is_not_dot);
	$entries = array_filter($entries, $is_not_dangerous);

	return $entries;
}

function getServerPorts() {
	$files = scandir(ARMA3_PATH);
	$serverPorts = [];
	foreach ($files as $file) {
		$matches = [];
		if (preg_match('/^arma3server-([0-9]{4,5})$/', $file, $matches)) {
			$serverPorts[] = intval($matches[1]);
		}
	}


	return $serverPorts;
}

$givenSecret = isset($_REQUEST['secret']) ? trim($_REQUEST['secret']) : '';
$givenPort = isset($_POST['port']) ? intval($_POST['port']) : 0;
$givenTemplate = isset($_POST['template']) ? trim($_POST['template']) : '';
$wantsRestart = isset($_POST['restart']);

$user = '';
if ($givenSecret && in_array($givenSecret, $secrets, true)) {
	$user = array_flip($secrets)[$givenSecret];
}


if ($wantsRestart) {

	if (!$givenPort) {
		echo "invalid port";
		exit;
	}
	if ($givenTemplate && !in_array($givenTemplate, getTemplateNames(), true)) {
		echo "invalid template name given";
		exit;
	}

	if (!$user) {
		echo "I dont know you.";
		exit;
	}

	$date = date('Y-m-d H:i:s');
	syslog(LOG_INFO, "arma3server $givenPort restart triggered at $date by $user");

	triggerRestart($user, $givenPort, $givenTemplate);
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Gruppe Adler Server Manager</title>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
	<link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css"/>
	<link rel="stylesheet" type="text/css" href="restart.css"/>
	<style>
		body {
			margin: 1em;
		}
	</style>
</head>
<body>
<img src="https://www.gruppe-adler.de/wp-content/uploads/160x160_ocker.png" width="80" height="80">
<? if ($wantsRestart) {
	$requestNumber = explode("\n", trim(file_get_contents(RESTART_TRIGGER_FILENAME)), 100);
	?>
	<div class="alert alert-success">
		<h1>Neustart für <?= $givenPort ?> ausgelöst (<?= count($requestNumber) ?>)</h1>
	</div>
	<?
	if (count($requestNumber) > 3) {
		?>
		<div class="alert alert-warning">
			Es sind schon einige Neustart-Anweisungen aufgelaufen -- evtl ist hier was kaputt :(
		</div>
		<?
	}
	?>
<? } else { ?>
<h1>Server Neustart</h1> <? } 
?>
<form name="restart" method="post" action="">
	<div class="input-group">
		<label>Authentification
		<br>
			<input type="text" name="secret" value="<?= $givenSecret ?>" required/>
		</label>
		<input type="hidden" name="restart" value="1"/>
	</div>
	<div class="input-group">
		<label>
			Port
			<div class="selection">
			<select name="port">
				<option value="" selected>bitte w&auml;hlen</option>
				<?php foreach (getServerPorts() as $serverPort) {?>
					<option value="<?= $serverPort ?>"><?= $serverPort?></option>
				<?php } ?>
			</select>
			</div>
		</label>
	</div>
	<div class="input-group">
		<label>
			Konfiguration
			<div class="selection">
			<select name="template">
				<option value="" selected>nicht &auml;ndern</option>
				<?php foreach (getTemplateNames() as $templateName) { ?>
					<option value="<?= $templateName ?>"><?= $templateName ?></option>
				<?php } ?>
			</select>
			</div>
		</label>
	</div>
	<div class="btn_container">
	<div>
		<button class="button" type="submit"><span>KILL</span></button>
		</div>
	</div>
</form>
<br><br>
<a href="https://arma3-servers.net/server/109969/" style="display: innline-block; margin-top: 20px"><img src="https://arma3-servers.net/server/109969/banners/half-banner-2.png" border="0"></a><a href="https://arma3-servers.net/server/109971/" style="margin-left:20px"><img src="https://arma3-servers.net/server/109971/banners/half-banner-2.png" border="0"></a>

<script>

	(function () {
		var
			$authenticationInput = $('form[name=restart] input[name=secret]'),
			auth = localStorage.getItem('adlertools-secret');

		$authenticationInput.change(function () {
			auth = this.value;
			localStorage.setItem('adlertools-secret', auth);
		});

		$authenticationInput.val(auth);
	}());
</script>
</body>
</html>
