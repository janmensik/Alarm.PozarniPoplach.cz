<?php

# Smarty templates
$smarty_plugins = array(
    //'czmonth' => 'modifier',
    //'czday' => 'modifier',
    'czech_num_items' => 'modifier',
    'nice_num' => 'modifier',
    'nl2br' => 'modifier',
    'agots' => 'function',
    'ppurl' => 'function',
    //'nl2p' => 'modifier',
    //'utf2ascii' => 'modifier',
    'thumb' => 'function'
);
//require_once(__DIR__ . "/lib/functions/class.url_parameters.php");

# Smarty templates
use Smarty\Smarty;

$Smarty = new Smarty();
$Smarty->setTemplateDir($_ENV['SMARTY_TEMPLATE_DIR']);
$Smarty->setConfigDir($_ENV['SMARTY_TEMPLATE_DIR']);
$Smarty->setCompileDir($_ENV['SMARTY_COMPILE_DIR']);


$Smarty->compile_check = $_ENV['DEBUGGING'];
if ($_ENV['DEBUGGING'] === 2)
	$Smarty->debugging = true;
$Smarty->error_reporting = E_ALL ^ E_WARNING;

# Smarty plugins
if (is_array($smarty_plugins))
	foreach ($smarty_plugins as $smarty_plugin => $smarty_type) {
		switch ($smarty_type) {
			case 'modifier':
				$smarty_type = Smarty::PLUGIN_MODIFIER;
				break;
			case 'function':
			default:
				$smarty_type = Smarty::PLUGIN_FUNCTION;
		}

		require_once(__DIR__ . '/lib/smarty-plugins/' . $smarty_type . '.' . $smarty_plugin . '.php');
		$Smarty->registerPlugin($smarty_type, $smarty_plugin, 'smarty_' . $smarty_type . '_' . $smarty_plugin);
	}


# Smarty load global config
$Smarty->config_overwrite = false;
$Smarty->configLoad(__DIR__ . '/tpl/app.conf', 'pages');

$APPD->setData('CONFIG', $Smarty->getConfigVars());
